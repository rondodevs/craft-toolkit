<?php

namespace rondodevs\toolkit\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Html;
use craft\helpers\Json as JsonHelper;
use rondodevs\toolkit\assets\orgschemafield\OrgSchemaFieldAsset;
use rondodevs\toolkit\graphql\types\OrgSchemaFieldPieceType;
use GraphQL\Type\Definition\Type;
use yii\db\Schema;

/**
 * Lets editors attach one or more schema.org structured data pieces to an
 * individual element (e.g. Article, Product, Event, FAQPage...), on top of
 * the site-wide Organization defaults configured in the Toolkit "Org Schema"
 * panel. Each piece is a "@type" (picked from a searchable combobox, or
 * freely typed) plus either a small set of type-specific "basic" fields
 * edited via a normal UI, or a raw JSON object for anything the basic fields
 * don't cover. Pieces are merged and exposed via GraphQL as a ready-to-use
 * JSON string (see OrgSchemaFieldPieceType::json).
 */
class OrgSchemaField extends Field
{
    public const CURATED_TYPES = [
        'Article',
        'BlogPosting',
        'NewsArticle',
        'BreadcrumbList',
        'Course',
        'Dentist',
        'Event',
        'FAQPage',
        'Hospital',
        'HowTo',
        'ImageObject',
        'JobPosting',
        'LocalBusiness',
        'MedicalBusiness',
        'MedicalClinic',
        'Organization',
        'Person',
        'Pharmacy',
        'Physician',
        'Product',
        'Recipe',
        'Review',
        'VideoObject',
        'WebPage',
        'WebSite',
    ];

    /**
     * Human-friendly labels for the basic-field property keys below.
     */
    public const PROPERTY_LABELS = [
        'name' => 'Name',
        'legalName' => 'Legal name',
        'description' => 'Description',
        'url' => 'URL',
        'image' => 'Image URL',
        'logo' => 'Logo URL',
        'email' => 'Email',
        'telephone' => 'Telephone',
        'jobTitle' => 'Job title',
        'worksFor' => 'Works for (organization)',
        'sku' => 'SKU',
        'brand' => 'Brand',
        'price' => 'Price',
        'priceCurrency' => 'Price currency (ISO 4217, e.g. EUR)',
        'priceRange' => 'Price range (e.g. $$)',
        'availability' => 'Availability (e.g. InStock, OutOfStock)',
        'startDate' => 'Start date',
        'endDate' => 'End date',
        'location' => 'Location',
        'organizer' => 'Organizer',
        'headline' => 'Headline',
        'datePublished' => 'Date published',
        'dateModified' => 'Date modified',
        'author' => 'Author name',
        'publisher' => 'Publisher',
        'articleSection' => 'Section',
        'reviewBody' => 'Review body',
        'thumbnailUrl' => 'Thumbnail URL',
        'uploadDate' => 'Upload date',
        'duration' => 'Duration (ISO 8601, e.g. PT1H30M)',
        'contentUrl' => 'Content URL',
        'embedUrl' => 'Embed URL',
        'caption' => 'Caption',
        'title' => 'Title',
        'datePosted' => 'Date posted',
        'employmentType' => 'Employment type',
        'hiringOrganization' => 'Hiring organization',
        'jobLocation' => 'Job location',
        'baseSalary' => 'Base salary',
        'provider' => 'Provider',
        'courseCode' => 'Course code',
        'prepTime' => 'Prep time (ISO 8601)',
        'cookTime' => 'Cook time (ISO 8601)',
        'totalTime' => 'Total time (ISO 8601)',
        'recipeYield' => 'Recipe yield (e.g. 4 servings)',
        'estimatedCost' => 'Estimated cost',
        'foundingDate' => 'Founding date',
        'openingHours' => 'Opening hours (e.g. Mo-Fr 09:00-18:00)',
        'address' => 'Address (single line)',
        'streetAddress' => 'Street address',
        'addressLocality' => 'City',
        'addressRegion' => 'Region / State',
        'postalCode' => 'Postal code',
        'addressCountry' => 'Country code (e.g. US)',
        'latitude' => 'Latitude (e.g. 41.9028)',
        'longitude' => 'Longitude (e.g. 12.4964)',
        'inLanguage' => 'Language (e.g. en-US)',
        'position' => 'Position (order in the list)',
    ];

    /**
     * Input type overrides for basic-field property keys (defaults to "text").
     */
    public const PROPERTY_INPUT_TYPES = [
        'url' => 'url',
        'image' => 'url',
        'logo' => 'url',
        'thumbnailUrl' => 'url',
        'contentUrl' => 'url',
        'embedUrl' => 'url',
        'email' => 'email',
        'datePublished' => 'date',
        'dateModified' => 'date',
        'startDate' => 'date',
        'endDate' => 'date',
        'uploadDate' => 'date',
        'datePosted' => 'date',
        'foundingDate' => 'date',
        'description' => 'textarea',
        'reviewBody' => 'textarea',
        'address' => 'textarea',
        'position' => 'number',
    ];

    /**
     * Shared "location" field set for LocalBusiness and its Medical subtypes:
     * NAP (name/address/telephone) + geo-coordinates + openingHours +
     * priceRange, the properties that matter most for local/maps visibility.
     * streetAddress/addressLocality/addressRegion/postalCode/addressCountry
     * are merged into a nested "address" object, and latitude/longitude into
     * a nested "geo" object, by buildPieces().
     */
    private const LOCATION_FIELDS = [
        'name', 'image', 'telephone', 'email',
        'streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry',
        'latitude', 'longitude',
        'openingHours', 'priceRange', 'url', 'description',
    ];

    /**
     * The keys nested under "address" (as a PostalAddress) when present.
     */
    private const ADDRESS_KEYS = ['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry'];

    /**
     * The keys nested under "geo" (as GeoCoordinates) when present.
     */
    private const GEO_KEYS = ['latitude', 'longitude'];

    /**
     * The "basic" property keys offered via UI inputs for each curated @type.
     * Properties that inherently need an array value (sameAs, mainEntity,
     * recipeIngredient/Instructions, itemListElement, ...) are intentionally
     * left out — use the JSON mode for those.
     */
    public const TYPE_PROPERTY_FIELDS = [
        'Organization' => ['name', 'legalName', 'url', 'logo', 'image', 'email', 'telephone', 'foundingDate', 'description'],
        'LocalBusiness' => self::LOCATION_FIELDS,
        'MedicalBusiness' => self::LOCATION_FIELDS,
        'MedicalClinic' => self::LOCATION_FIELDS,
        'Dentist' => self::LOCATION_FIELDS,
        'Physician' => self::LOCATION_FIELDS,
        'Hospital' => self::LOCATION_FIELDS,
        'Pharmacy' => self::LOCATION_FIELDS,
        'Person' => ['name', 'url', 'image', 'jobTitle', 'worksFor', 'email', 'telephone', 'description'],
        'Article' => ['headline', 'description', 'image', 'datePublished', 'dateModified', 'author', 'publisher', 'articleSection'],
        'BlogPosting' => ['headline', 'description', 'image', 'datePublished', 'dateModified', 'author', 'publisher', 'articleSection'],
        'NewsArticle' => ['headline', 'description', 'image', 'datePublished', 'dateModified', 'author', 'publisher', 'articleSection'],
        'Product' => ['name', 'description', 'image', 'sku', 'brand', 'price', 'priceCurrency', 'availability', 'url'],
        'Event' => ['name', 'description', 'startDate', 'endDate', 'location', 'organizer', 'image', 'url'],
        'Review' => ['name', 'reviewBody', 'author', 'image', 'datePublished'],
        'WebPage' => ['name', 'description', 'url', 'image', 'datePublished', 'dateModified'],
        'WebSite' => ['name', 'description', 'url', 'inLanguage'],
        'VideoObject' => ['name', 'description', 'thumbnailUrl', 'contentUrl', 'embedUrl', 'uploadDate', 'duration'],
        'ImageObject' => ['name', 'description', 'url', 'contentUrl', 'caption'],
        'FAQPage' => ['name', 'description'],
        'HowTo' => ['name', 'description', 'image', 'totalTime', 'estimatedCost'],
        'BreadcrumbList' => ['name', 'position'],
        'JobPosting' => ['title', 'description', 'datePosted', 'employmentType', 'hiringOrganization', 'jobLocation', 'baseSalary'],
        'Course' => ['name', 'description', 'provider', 'courseCode', 'url'],
        'Recipe' => ['name', 'description', 'image', 'prepTime', 'cookTime', 'totalTime', 'recipeYield'],
    ];

    /**
     * Fallback basic fields offered for any @type not listed above (including
     * freely-typed custom types), so a UI is always available.
     */
    public const DEFAULT_PROPERTY_FIELDS = ['name', 'description', 'url', 'image'];

    public static function displayName(): string
    {
        return 'Org Schema';
    }

    public static function icon(): string
    {
        return 'brackets-curly';
    }

    public static function phpType(): string
    {
        return 'array|null';
    }

    public static function dbType(): array|string|null
    {
        return Schema::TYPE_TEXT;
    }

    /**
     * Returns the basic-field definitions (key/label/inputType) for a given
     * schema.org @type, falling back to a generic baseline for types that
     * don't have a curated field set.
     */
    public static function basicFieldsForType(string $type): array
    {
        $keys = self::TYPE_PROPERTY_FIELDS[$type] ?? self::DEFAULT_PROPERTY_FIELDS;
        $fields = [];

        foreach ($keys as $key) {
            $fields[] = [
                'key' => $key,
                'label' => self::PROPERTY_LABELS[$key] ?? ucfirst($key),
                'inputType' => self::PROPERTY_INPUT_TYPES[$key] ?? 'text',
            ];
        }

        return $fields;
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            return [];
        }

        return self::buildPieces($value);
    }

    public function normalizeValueFromRequest(mixed $value, ?ElementInterface $element): mixed
    {
        if (!is_array($value)) {
            return [];
        }

        $rows = is_array($value['rows'] ?? null) ? $value['rows'] : [];

        return self::buildPieces($rows);
    }

    private static function buildPieces(array $rows): array
    {
        $pieces = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $type = trim((string)($row['type'] ?? ''));
            $mode = ($row['mode'] ?? 'ui') === 'json' ? 'json' : 'ui';
            $rawProps = is_array($row['props'] ?? null) ? $row['props'] : [];
            $propertiesJsonRaw = trim((string)($row['propertiesJson'] ?? ''));

            $hasError = false;
            $properties = [];

            if ($mode === 'json') {
                if ($propertiesJsonRaw !== '') {
                    $decoded = json_decode($propertiesJsonRaw, true);

                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $properties = $decoded;
                    } else {
                        $hasError = true;
                    }
                }
            } else {
                foreach (self::basicFieldsForType($type) as $field) {
                    $key = $field['key'];
                    $fieldValue = trim((string)($rawProps[$key] ?? ''));

                    if ($fieldValue !== '') {
                        $properties[$key] = $fieldValue;
                    }
                }
            }

            if ($type === '' && $properties === [] && $propertiesJsonRaw === '') {
                continue;
            }

            $outputProperties = $mode === 'json' ? $properties : self::nestStructuredProperties($properties);
            $mergedPiece = ['@type' => $type !== '' ? $type : 'Thing'] + $outputProperties;

            $pieces[] = [
                'type' => $type,
                'mode' => $mode,
                // Kept flat (not nested) so the basic-field inputs can be re-populated as-is.
                'props' => $properties,
                'propertiesJson' => $mode === 'json' ? $propertiesJsonRaw : '',
                'hasError' => $hasError,
                'json' => json_encode($mergedPiece, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ];
        }

        return $pieces;
    }

    /**
     * Moves flat address/geo basic-field values into nested "address"
     * (PostalAddress) and "geo" (GeoCoordinates) objects for JSON-LD output.
     */
    private static function nestStructuredProperties(array $properties): array
    {
        $address = [];

        foreach (self::ADDRESS_KEYS as $key) {
            if (array_key_exists($key, $properties)) {
                $address[$key] = $properties[$key];
                unset($properties[$key]);
            }
        }

        if ($address !== []) {
            $properties['address'] = ['@type' => 'PostalAddress'] + $address;
        }

        $geo = [];

        foreach (self::GEO_KEYS as $key) {
            if (array_key_exists($key, $properties)) {
                $geo[$key] = $properties[$key];
                unset($properties[$key]);
            }
        }

        if ($geo !== []) {
            $properties['geo'] = ['@type' => 'GeoCoordinates'] + $geo;
        }

        return $properties;
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(OrgSchemaFieldAsset::class);

        $rows = is_array($value) ? $value : [];

        $rows = array_map(static function(array $piece): array {
            $type = (string)($piece['type'] ?? '');
            $piece['basicFields'] = OrgSchemaField::basicFieldsForType($type);

            $selectableTypes = self::CURATED_TYPES;
            if ($type !== '' && !in_array($type, $selectableTypes, true)) {
                array_unshift($selectableTypes, $type);
            }
            $piece['selectableTypes'] = $selectableTypes;

            return $piece;
        }, $rows);

        $typeFields = [];

        foreach (self::TYPE_PROPERTY_FIELDS as $type => $keys) {
            $typeFields[$type] = self::basicFieldsForType($type);
        }

        return $view->renderTemplate('toolkit/fields/org-schema/input', [
            'namePrefix' => $this->handle,
            'rows' => $rows,
            'types' => self::CURATED_TYPES,
            'fieldConfigJson' => JsonHelper::encode([
                'typeFields' => $typeFields,
                'defaultFields' => self::basicFieldsForType('__default__'),
            ]),
        ]);
    }

    public function getElementValidationRules(): array
    {
        return [
            [
                function(ElementInterface $element) {
                    $value = $element->getFieldValue($this->handle);

                    if (!is_array($value)) {
                        return;
                    }

                    foreach ($value as $piece) {
                        if (!is_array($piece)) {
                            continue;
                        }

                        if (!empty($piece['hasError'])) {
                            $element->addError("field:$this->handle", Craft::t('app', '{attribute} has a row with invalid JSON properties.', [
                                'attribute' => $this->getUiLabel(),
                            ]));
                            return;
                        }

                        if (empty($piece['type'])) {
                            $element->addError("field:$this->handle", Craft::t('app', '{attribute} has a row missing a schema.org type.', [
                                'attribute' => $this->getUiLabel(),
                            ]));
                            return;
                        }
                    }
                },
            ],
        ];
    }

    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        return !is_array($value) || $value === [];
    }

    public function serializeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if (!is_array($value)) {
            return null;
        }

        $stored = [];

        foreach ($value as $piece) {
            if (!is_array($piece)) {
                continue;
            }

            $stored[] = [
                'type' => (string)($piece['type'] ?? ''),
                'mode' => ($piece['mode'] ?? 'ui') === 'json' ? 'json' : 'ui',
                'props' => is_array($piece['props'] ?? null) ? $piece['props'] : [],
                'propertiesJson' => (string)($piece['propertiesJson'] ?? ''),
            ];
        }

        if ($stored === []) {
            return null;
        }

        return json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function getContentGqlType(): Type|array
    {
        return Type::listOf(OrgSchemaFieldPieceType::getType());
    }

    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        if (!is_array($value) || $value === []) {
            return '';
        }

        $count = count($value);

        return Html::tag('code', $count . ' schema.org ' . ($count === 1 ? 'entry' : 'entries'));
    }
}
