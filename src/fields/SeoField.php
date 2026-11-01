<?php

namespace rondodevs\toolkit\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Asset;
use craft\fields\Assets;
use craft\fields\PlainText;
use craft\helpers\Html;
use craft\helpers\Json as JsonHelper;
use GraphQL\Type\Definition\Type;
use rondodevs\toolkit\assets\seofield\SeoFieldAsset;
use rondodevs\toolkit\graphql\types\SeoType;
use rondodevs\toolkit\seo\EtherLegacy;
use rondodevs\toolkit\seo\SeoData;
use yii\db\Schema;

/**
 * Per-entry SEO: title, description, social image and texts, robots,
 * canonical URL and AI summary. Anything left empty is filled from rules:
 * an ordered list of fields to take the title, description and image from,
 * then an object template (Twig) as the last fallback. Meant as one global
 * field: each entry type can have its own rules, set on the field in its
 * field layout; the field's settings are the rules where none are set. Replaces Ether SEO's field: it reads Ether's stored values, so
 * an Ether field can be switched to this type in place (see SeoService).
 */
class SeoField extends Field
{
    /** @var string[] "title" or "field:<handle>", first non-empty wins */
    public array $titleSources = [];
    public string $titleTemplate = '';
    public string $titlePrefix = '';
    public string $titleSuffix = ' - {{ siteName }}';

    /** @var string[] "field:<handle>", first non-empty wins */
    public array $descriptionSources = [];
    public string $descriptionTemplate = '';
    /** Automatic descriptions are cut to this length; 0 keeps them whole */
    public int $descriptionMaxLength = 160;

    /** @var string[] Assets field handles, first image wins */
    public array $imageSources = [];
    /** @var int[]|string|null Asset ID, as the element select posts it */
    public mixed $defaultImage = null;

    /** @var string[] */
    public array $defaultRobots = [];

    public static function displayName(): string
    {
        return 'SEO';
    }

    public static function icon(): string
    {
        return 'magnifying-glass';
    }

    public static function phpType(): string
    {
        return SeoData::class;
    }

    public static function dbType(): array|string|null
    {
        return Schema::TYPE_TEXT;
    }

    /**
     * The settings form posts empty lists as "" and numbers as strings.
     */
    public function __construct(array $config = [])
    {
        foreach (['titleSources', 'descriptionSources', 'imageSources'] as $key) {
            if (array_key_exists($key, $config)) {
                $config[$key] = self::normalizeSources($config[$key]);
            }
        }

        if (array_key_exists('defaultRobots', $config)) {
            $config['defaultRobots'] = array_values(array_intersect(SeoData::ROBOTS_OPTIONS, (array)$config['defaultRobots']));
        }

        if (array_key_exists('descriptionMaxLength', $config)) {
            $config['descriptionMaxLength'] = max(0, (int)$config['descriptionMaxLength']);
        }

        foreach (['titleTemplate', 'titlePrefix', 'titleSuffix', 'descriptionTemplate'] as $key) {
            if (array_key_exists($key, $config)) {
                $config[$key] = (string)$config[$key];
            }
        }

        parent::__construct($config);
    }

    public function defaultImageId(): ?int
    {
        $id = is_array($this->defaultImage) ? reset($this->defaultImage) : $this->defaultImage;

        return is_numeric($id) && (int)$id > 0 ? (int)$id : null;
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if ($value instanceof SeoData) {
            return $value;
        }

        if (is_string($value)) {
            $value = JsonHelper::decodeIfJson($value);
        }

        if (!is_array($value)) {
            return new SeoData($this, $element);
        }

        if (EtherLegacy::isEtherValue($value)) {
            return $this->normalizeEtherValue($value, $element);
        }

        return new SeoData($this, $element, $value);
    }

    /**
     * Ether saved its computed defaults as if the editor had typed them, so
     * a value only stays custom when it differs from what this field
     * computes: entries keep rendering the same and follow title changes
     * again from here on.
     */
    private function normalizeEtherValue(array $value, ?ElementInterface $element): SeoData
    {
        $values = EtherLegacy::candidateValues($value);
        $auto = new SeoData($this, $element);

        if ($values['title'] === $auto->getAutoBaseTitle() || $values['title'] === $auto->getAutoTitle()) {
            $values['title'] = '';
        } elseif ($values['title'] !== '') {
            // Ether kept its locked tokens around a custom title; here a custom title is the whole title
            $values['title'] = $auto->getTitlePrefix() . $values['title'] . $auto->getTitleSuffix();
        }

        if ($values['description'] === $auto->getAutoDescription()) {
            $values['description'] = '';
        }

        $data = new SeoData($this, $element, $values);

        if ($values['ogTitle'] === $data->getTitle() || $values['ogTitle'] === $auto->getAutoBaseTitle()) {
            $data->ogTitle = '';
        }

        if ($values['ogDescription'] === $data->getDescription()) {
            $data->ogDescription = '';
        }

        return new SeoData($this, $element, $data->toArray());
    }

    /**
     * Title and description come prefilled with their automatic values: an
     * untouched one (flagged by the input, or equal to the automatic value)
     * is saved empty, so it stays automatic.
     */
    public function normalizeValueFromRequest(mixed $value, ?ElementInterface $element): mixed
    {
        $value = is_array($value) ? $value : [];
        $auto = new SeoData($this, $element);

        if (($value['titleAuto'] ?? '0') === '1' || trim((string)($value['title'] ?? '')) === $auto->getAutoTitle()) {
            $value['title'] = '';
        }

        if (($value['descriptionAuto'] ?? '0') === '1' || trim((string)($value['description'] ?? '')) === $auto->getAutoDescription()) {
            $value['description'] = '';
        }

        return new SeoData($this, $element, $value);
    }

    public function serializeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if (!$value instanceof SeoData || $value->isEmpty()) {
            return null;
        }

        return $value->toArray();
    }

    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        return !$value instanceof SeoData || $value->isEmpty();
    }

    public function getElementValidationRules(): array
    {
        return [
            [
                function(ElementInterface $element) {
                    $value = $element->getFieldValue($this->handle);

                    if ($value instanceof SeoData && $value->canonical !== '' && !preg_match('#^(https?://|/)#i', $value->canonical)) {
                        $element->addError("field:$this->handle", Craft::t('app', 'The canonical URL must start with https://, http:// or /.'));
                    }
                },
            ],
        ];
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        if (!$value instanceof SeoData) {
            $value = new SeoData($this, $element);
        }

        $view = Craft::$app->getView();
        $view->registerAssetBundle(SeoFieldAsset::class);

        $customImage = $value->getCustomImage();
        $autoImage = $value->getAutoImage();

        return $view->renderTemplate('toolkit/fields/seo/input', [
            'name' => $this->handle,
            'value' => $value,
            'auto' => [
                'title' => $value->getAutoTitle(),
                'titleSource' => $this->sourceLabel($value->getAutoTitleSource()),
                'description' => $value->getAutoDescription(),
                'descriptionSource' => $this->sourceLabel($value->getAutoDescriptionSource()),
                'image' => $autoImage,
            ],
            'customImage' => $customImage,
            'previewUrl' => $element?->getUrl(),
            'preview' => [
                'siteName' => $element?->getSite()->getName() ?? Craft::$app->getSites()->getCurrentSite()->getName(),
                'domain' => parse_url((string)($element?->getUrl() ?? $element?->getSite()->getBaseUrl() ?? ''), PHP_URL_HOST) ?: '',
                'customImage' => $customImage ? self::previewImage($customImage) : null,
                'autoImage' => $autoImage ? self::previewImage($autoImage) : null,
            ],
            'robotsOptions' => SeoData::ROBOTS_OPTIONS,
            'defaultRobots' => $this->defaultRobots,
            'descriptionMaxLength' => $this->descriptionMaxLength ?: 160,
        ]);
    }

    /**
     * An image as the social previews show it: a CP thumbnail at the size
     * networks crop to, and the real size for the size check.
     */
    public static function previewImage(Asset $asset): array
    {
        return [
            'url' => Craft::$app->getAssets()->getThumbUrl($asset, 1200, 630),
            'width' => $asset->getWidth(),
            'height' => $asset->getHeight(),
            'filename' => $asset->filename,
        ];
    }

    public function getSettingsHtml(): ?string
    {
        $textFields = [];
        $assetFields = [];

        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            if ($field->id === $this->id) {
                continue;
            }

            if ($field instanceof Assets) {
                $assetFields[$field->handle] = "$field->name ($field->handle)";
            } elseif (self::isTextField($field)) {
                $textFields['field:' . $field->handle] = "$field->name ($field->handle)";
            }
        }

        $defaultImageId = $this->defaultImageId();

        return Craft::$app->getView()->renderTemplate('toolkit/fields/seo/settings', [
            'field' => $this,
            'titleSourceOptions' => ['title' => Craft::t('app', 'Title')] + $textFields,
            'descriptionSourceOptions' => $textFields,
            'imageSourceOptions' => $assetFields,
            'robotsOptions' => SeoData::ROBOTS_OPTIONS,
            'defaultImage' => $defaultImageId ? Craft::$app->getAssets()->getAssetById($defaultImageId) : null,
        ]);
    }

    public function getSearchKeywords(mixed $value, ElementInterface $element): string
    {
        return $value instanceof SeoData ? $value->title . ' ' . $value->description : '';
    }

    public function getContentGqlType(): Type|array
    {
        return SeoType::getType();
    }

    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        if (!$value instanceof SeoData) {
            return '';
        }

        $robots = $value->getRobots();
        $noindex = in_array('noindex', $robots, true);

        return Html::tag('span', Html::encode($value->getTitle()), [
            'class' => $noindex ? 'light' : null,
            'title' => $noindex ? 'noindex' : null,
        ]);
    }

    /**
     * Fields whose value reads as text: plain text and rich text (CKEditor,
     * Redactor). Their HTML is stripped when used as a source.
     */
    public static function isTextField(mixed $field): bool
    {
        return $field instanceof PlainText
            || in_array($field::class, ['craft\\ckeditor\\Field', 'craft\\redactor\\Field'], true);
    }

    private function sourceLabel(string $source): string
    {
        if ($source === '') {
            return '';
        }

        if ($source === 'title') {
            return Craft::t('app', 'Title');
        }

        if ($source === 'template') {
            return Craft::t('app', 'template');
        }

        $handle = str_starts_with($source, 'field:') ? substr($source, 6) : $source;
        $field = Craft::$app->getFields()->getFieldByHandle($handle);

        return $field?->name ?? $handle;
    }

    /**
     * Sources arrive as plain strings (project config) or as editable table
     * rows (settings form).
     *
     * @return string[]
     */
    public static function normalizeSources(mixed $sources): array
    {
        $normalized = [];

        foreach ((array)$sources as $source) {
            if (is_array($source)) {
                $source = $source['source'] ?? '';
            }

            $source = trim((string)$source);

            if ($source !== '' && !in_array($source, $normalized, true)) {
                $normalized[] = $source;
            }
        }

        return $normalized;
    }
}
