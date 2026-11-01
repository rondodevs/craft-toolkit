<?php

namespace rondodevs\toolkit\seo;

use Craft;

/**
 * Everything the Toolkit SEO field needs to take over from Ether SEO
 * (ether/seo): reading the values Ether stored in element content, and
 * turning Ether's field and plugin settings into Toolkit SEO field settings
 * that render the same titles and descriptions.
 */
class EtherLegacy
{
    public const PLUGIN_HANDLE = 'seo';
    public const FIELD_CLASS = 'ether\\seo\\fields\\SeoField';

    private const VALUE_KEYS = ['titleRaw', 'descriptionRaw', 'keywords', 'score', 'social', 'advanced'];

    public static function isPluginEnabled(): bool
    {
        return Craft::$app->getPlugins()->isPluginEnabled(self::PLUGIN_HANDLE);
    }

    /**
     * The deprecated Ether_* GraphQL types are served by the toolkit only
     * while Ether SEO isn't loaded: with both, the type names would clash.
     */
    public static function isGraphqlCompatEnabled(): bool
    {
        return !self::isPluginEnabled();
    }

    public static function isEtherValue(array $value): bool
    {
        return array_intersect(self::VALUE_KEYS, array_keys($value)) !== [];
    }

    /**
     * The values of an Ether SEO field as Toolkit SEO values. Ether's CP
     * fills its inputs with the computed defaults and saves them as typed
     * text, so these are only candidates: SeoField drops the ones equal to
     * what the toolkit computes on its own (see SeoField::normalizeValue()).
     * Ether keeps Facebook and Twitter apart, the toolkit has one set of
     * social values: Facebook's win.
     */
    public static function candidateValues(array $value): array
    {
        $titleRaw = $value['titleRaw'] ?? $value['title'] ?? '';
        $title = '';

        if (is_string($titleRaw)) {
            $title = $titleRaw;
        } elseif (is_array($titleRaw)) {
            foreach ($titleRaw as $token) {
                if (is_string($token) && trim($token) !== '') {
                    $title = $token;
                    break;
                }
            }
        }

        $social = is_array($value['social'] ?? null) ? $value['social'] : [];
        $networks = [
            is_array($social['facebook'] ?? null) ? $social['facebook'] : [],
            is_array($social['twitter'] ?? null) ? $social['twitter'] : [],
        ];

        $advanced = is_array($value['advanced'] ?? null) ? $value['advanced'] : [];

        return [
            'title' => SeoData::plainText($title),
            'description' => self::string($value['descriptionRaw'] ?? $value['description'] ?? ''),
            'ogTitle' => self::firstSocial($networks, 'title'),
            'ogDescription' => self::firstSocial($networks, 'description'),
            'imageId' => self::firstSocialImage($networks),
            'robots' => is_array($advanced['robots'] ?? null) ? array_filter($advanced['robots']) : [],
            'canonical' => self::string($advanced['canonical'] ?? ''),
        ];
    }

    /**
     * Toolkit SEO field settings equivalent to an Ether SEO field's settings,
     * falling back to Ether's plugin settings where the field has none, as
     * Ether does.
     *
     * Ether's title is a list of tokens: the unlocked one is what editors
     * override, the locked ones are always shown. The unlocked token's
     * template becomes the title template, the locked tokens before and
     * after it the prefix and suffix of the automatic title (custom titles
     * get them added when read, see SeoField::normalizeEtherValue()).
     */
    public static function fieldSettings(array $fieldSettings, array $pluginSettings): array
    {
        $tokens = self::setting('title', $fieldSettings, $pluginSettings);
        $tokens = is_array($tokens) ? array_values(array_filter($tokens, 'is_array')) : [];

        $prefix = '';
        $suffix = '';
        $template = '';
        $unlockedFound = false;

        foreach ($tokens as $token) {
            $tokenTemplate = (string)($token['template'] ?? '');
            $locked = !empty($token['locked']) && $token['locked'] !== '0';

            if (!$unlockedFound && !$locked) {
                $template = $tokenTemplate;
                $unlockedFound = true;
            } elseif ($unlockedFound) {
                $suffix .= $tokenTemplate;
            } else {
                $prefix .= $tokenTemplate;
            }
        }

        if (!$unlockedFound) {
            // Nothing editable: the whole title is fixed
            $template = $prefix;
            $prefix = '';
        }

        if (trim($template) === '{title}') {
            // The element title is already the default
            $template = '';
        }

        $robots = array_values(array_intersect(
            SeoData::ROBOTS_OPTIONS,
            array_filter((array)($pluginSettings['robots'] ?? []))
        ));

        return [
            'titleSources' => [],
            'titleTemplate' => $template,
            'titlePrefix' => $prefix,
            'titleSuffix' => $suffix,
            'descriptionSources' => [],
            'descriptionTemplate' => (string)self::setting('description', $fieldSettings, $pluginSettings),
            // Ether's descriptions were never cut
            'descriptionMaxLength' => 0,
            'imageSources' => [],
            'defaultImage' => self::assetIds(self::setting('socialImage', $fieldSettings, $pluginSettings)),
            'defaultRobots' => $robots,
        ];
    }

    /**
     * Ether SEO's plugin settings, from the plugin while it's installed or
     * from the project config otherwise.
     */
    public static function pluginSettings(): array
    {
        $plugin = Craft::$app->getPlugins()->getPlugin(self::PLUGIN_HANDLE);

        if ($plugin !== null && $plugin->getSettings() !== null) {
            return $plugin->getSettings()->toArray();
        }

        $settings = Craft::$app->getProjectConfig()->get('plugins.' . self::PLUGIN_HANDLE . '.settings');

        return is_array($settings) ? $settings : [];
    }

    private static function setting(string $key, array $fieldSettings, array $pluginSettings): mixed
    {
        return empty($fieldSettings[$key]) ? ($pluginSettings[$key] ?? null) : $fieldSettings[$key];
    }

    private static function firstSocial(array $networks, string $key): string
    {
        foreach ($networks as $network) {
            $value = self::string($network[$key] ?? '');

            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private static function firstSocialImage(array $networks): ?int
    {
        foreach ($networks as $network) {
            $ids = self::assetIds($network['imageId'] ?? $network['image'] ?? null);

            if ($ids !== []) {
                return $ids[0];
            }
        }

        return null;
    }

    /**
     * Ether stores asset IDs as "12", 12, ["12"] or [["id" => 12]].
     *
     * @return int[]
     */
    private static function assetIds(mixed $value): array
    {
        $ids = [];

        foreach ((array)$value as $item) {
            $id = is_array($item) ? ($item['id'] ?? null) : $item;

            if (is_numeric($id) && (int)$id > 0) {
                $ids[] = (int)$id;
            }
        }

        return $ids;
    }

    /**
     * Ether serializes computed values that were never typed as [] or {}.
     */
    private static function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
