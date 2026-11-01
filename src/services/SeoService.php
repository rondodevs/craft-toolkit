<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\db\Query;
use craft\fields\Assets;
use craft\fields\MissingField;
use craft\models\EntryType;
use craft\models\FieldLayout;
use rondodevs\toolkit\fields\SeoField;
use rondodevs\toolkit\seo\EtherLegacy;
use RuntimeException;
use yii\base\Component;

/**
 * The SEO field's rules per entry type, and taking projects off Ether SEO:
 * every Ether SEO field is switched to the Toolkit SEO type in place (same
 * ID, UID and handle, so element content and the `seo` GraphQL field stay
 * where they are) and Ether is uninstalled.
 */
class SeoService extends Component
{
    private const ETHER_REDIRECTS_TABLE = '{{%seo_redirects}}';

    /**
     * Rules set on the field in a field layout (its settings slideout in the
     * layout designer), keyed by layout element UID. Kept in the toolkit's
     * own project config rather than the layout config: an unknown key there
     * would break every layout loading it if the toolkit were ever disabled.
     */
    public const LAYOUT_CONFIG_KEY = 'toolkit.seo.layoutElements';

    /** The rules a layout can set; the field's settings fill the rest */
    public const RULE_KEYS = ['titleSources', 'titleTemplate', 'descriptionSources', 'descriptionTemplate', 'imageSources'];

    private const SOURCE_KEYS = ['titleSources', 'descriptionSources', 'imageSources'];

    private ?array $layoutRules = null;

    /**
     * The field's rules, each replaced by the rule set where the field sits
     * in the element's layout, when set (a non-empty list or template).
     */
    public function rulesFor(SeoField $field, ?ElementInterface $element): array
    {
        $rules = [];

        foreach (self::RULE_KEYS as $key) {
            $rules[$key] = $field->$key;
        }

        $layoutRules = $this->getRulesForLayoutElement($field->layoutElement?->uid);

        foreach (self::RULE_KEYS as $key) {
            if (!empty($layoutRules[$key])) {
                $rules[$key] = $layoutRules[$key];
            }
        }

        return $rules;
    }

    public function getRulesForLayoutElement(?string $uid): array
    {
        if ($uid === null) {
            return [];
        }

        if ($this->layoutRules === null) {
            $config = Craft::$app->getProjectConfig()->get(self::LAYOUT_CONFIG_KEY);
            $this->layoutRules = is_array($config) ? $config : [];
        }

        return $this->layoutRules[$uid] ?? [];
    }

    /**
     * @param array $rules As the layout element settings form posts them
     */
    public function saveRulesForLayoutElement(string $uid, array $rules): void
    {
        $rules = $this->normalizeRules($rules);
        $path = self::LAYOUT_CONFIG_KEY . '.' . $uid;
        $projectConfig = Craft::$app->getProjectConfig();

        if ($rules === []) {
            $projectConfig->remove($path, 'Remove SEO field rules');
        } else {
            $projectConfig->set($path, $rules, 'Save SEO field rules');
        }

        $this->layoutRules = null;
        Craft::$app->getGql()->flushCaches();
    }

    /**
     * The fields of a layout that rules can read from.
     *
     * @return array{text: array<string, string>, image: array<string, string>}
     */
    public function sourceOptionsForLayout(?FieldLayout $layout): array
    {
        $text = ['title' => Craft::t('app', 'Title')];
        $image = [];

        foreach ($layout?->getCustomFields() ?? [] as $field) {
            if ($field instanceof Assets) {
                $image[$field->handle] = "$field->name ($field->handle)";
            } elseif (SeoField::isTextField($field)) {
                $text['field:' . $field->handle] = "$field->name ($field->handle)";
            }
        }

        return ['text' => $text, 'image' => $image];
    }

    /**
     * Where the SEO fields are used, and whether each place has its own rules.
     *
     * @return array<int, array{entryType: EntryType, field: SeoField, rules: array}>
     */
    public function getUsages(): array
    {
        $usages = [];

        foreach (Craft::$app->getEntries()->getAllEntryTypes() as $entryType) {
            foreach ($entryType->getFieldLayout()->getCustomFields() as $field) {
                if ($field instanceof SeoField) {
                    $usages[] = [
                        'entryType' => $entryType,
                        'field' => $field,
                        'rules' => $this->getRulesForLayoutElement($field->layoutElement?->uid),
                    ];
                }
            }
        }

        return $usages;
    }

    private function normalizeRules(array $rules): array
    {
        $normalized = [];

        foreach (self::RULE_KEYS as $key) {
            $value = in_array($key, self::SOURCE_KEYS, true)
                ? SeoField::normalizeSources($rules[$key] ?? [])
                : trim((string)($rules[$key] ?? ''));

            if ($value !== [] && $value !== '') {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    /**
     * Ether SEO fields, whether Ether is still installed or already gone
     * (then Craft loads them as missing fields).
     *
     * @return FieldInterface[]
     */
    public function getEtherFields(): array
    {
        return array_values(array_filter(
            Craft::$app->getFields()->getAllFields(),
            static fn(FieldInterface $field): bool => $field::class === EtherLegacy::FIELD_CLASS
                || ($field instanceof MissingField && $field->expectedType === EtherLegacy::FIELD_CLASS)
        ));
    }

    /**
     * @return SeoField[]
     */
    public function getToolkitFields(): array
    {
        return array_values(array_filter(
            Craft::$app->getFields()->getAllFields(),
            static fn(FieldInterface $field): bool => $field instanceof SeoField
        ));
    }

    public function canMigrate(): bool
    {
        return Craft::$app->getConfig()->getGeneral()->allowAdminChanges;
    }

    public function isEtherInstalled(): bool
    {
        return Craft::$app->getPlugins()->isPluginInstalled(EtherLegacy::PLUGIN_HANDLE);
    }

    /**
     * Whether ether/seo is still required by Composer, so it should be
     * removed from composer.json once uninstalled.
     */
    public function isEtherInComposer(): bool
    {
        return Craft::$app->getPlugins()->getComposerPluginInfo(EtherLegacy::PLUGIN_HANDLE) !== null;
    }

    /**
     * Ether's redirects aren't migrated: they're dropped with its tables.
     */
    public function getEtherRedirectCount(): int
    {
        $db = Craft::$app->getDb();

        if (!$db->tableExists(self::ETHER_REDIRECTS_TABLE)) {
            return 0;
        }

        return (int)(new Query())->from(self::ETHER_REDIRECTS_TABLE)->count();
    }

    /**
     * @return array{migrated: string[], uninstalled: bool}
     */
    public function migrateFromEther(bool $uninstall = true): array
    {
        if (!$this->canMigrate()) {
            throw new RuntimeException('Admin changes are disabled here (allowAdminChanges). Migrate in development and deploy the project config.');
        }

        // Read before uninstalling: Ether's settings go with it
        $pluginSettings = EtherLegacy::pluginSettings();
        $migrated = [];

        foreach ($this->getEtherFields() as $field) {
            $this->convertField($field, $pluginSettings);
            $migrated[] = $field->handle;
        }

        $uninstalled = false;

        if ($uninstall && $this->isEtherInstalled()) {
            $uninstalled = Craft::$app->getPlugins()->uninstallPlugin(EtherLegacy::PLUGIN_HANDLE);

            if (!$uninstalled) {
                throw new RuntimeException('The fields were converted, but Ether SEO could not be uninstalled. Uninstall it from Settings → Plugins: until then, queries on Ether_SeoData fail.');
            }
        }

        Craft::$app->getGql()->flushCaches();

        return ['migrated' => $migrated, 'uninstalled' => $uninstalled];
    }

    private function convertField(FieldInterface $field, array $pluginSettings): void
    {
        $etherSettings = $field instanceof MissingField ? (array)$field->settings : $field->getSettings();
        $fields = Craft::$app->getFields();

        $newField = $fields->createField([
            'type' => SeoField::class,
            'id' => $field->id,
            'uid' => $field->uid,
            'name' => $field->name,
            'handle' => $field->handle,
            'context' => $field->context,
            'columnSuffix' => $field->columnSuffix,
            'instructions' => $field->instructions,
            'searchable' => $field->searchable,
            'translationMethod' => $field->translationMethod,
            'translationKeyFormat' => $field->translationKeyFormat,
            'settings' => EtherLegacy::fieldSettings($etherSettings, $pluginSettings),
        ]);

        if (!$fields->saveField($newField)) {
            throw new RuntimeException("Unable to convert the \"$field->handle\" field: " . implode(' ', $newField->getFirstErrors()));
        }
    }
}
