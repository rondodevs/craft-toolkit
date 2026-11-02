<?php

namespace rondodevs\toolkit\seo;

use HumanMade\SchemaOrgValidator\Profiles;
use HumanMade\SchemaOrgValidator\RuleProfile;
use HumanMade\SchemaOrgValidator\Validator;
use HumanMade\SchemaOrgValidator\Vocabulary;

/**
 * schema.org structured data pieces, as the SEO field keeps them: each one a
 * "@type" (picked from the curated types or typed freely) plus either a few
 * type-specific basic fields or a raw JSON object. Each piece is resolved to
 * a ready-to-parse JSON-LD string (`json`), for Nuxt SEO's useSchemaOrg().
 * Pieces are checked against schema.org and Google's rich result rules
 * (humanmade/schema-org-validator) as editors type them.
 */
class SchemaPieces
{
    /**
     * Google profiles left out: Merchant listing is for shops selling on
     * Google (dozens of rules), Product snippet covers products here.
     */
    private const SKIPPED_PROFILES = ['google/merchant-listing'];

    private static ?Vocabulary $vocabulary = null;

    /** @var RuleProfile[]|null */
    private static ?array $profiles = null;

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
        'locationName' => 'Place name',
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
     * Types whose address belongs to where they happen: the place name and
     * address go in a nested "location" (Place), as Google wants for events.
     */
    private const LOCATION_TYPES = ['Event'];

    /**
     * Basic fields that were renamed, read from rows saved before: an
     * Event's single "location" text is now its place name.
     */
    private const RENAMED_PROPS = ['Event' => ['location' => 'locationName']];

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
        'Event' => [
            'name', 'description', 'startDate', 'endDate',
            'locationName', 'streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry',
            'organizer', 'image', 'url',
        ],
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

    public static function build(array $rows): array
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
                foreach (self::RENAMED_PROPS[$type] ?? [] as $old => $new) {
                    if (trim((string)($rawProps[$new] ?? '')) === '' && isset($rawProps[$old])) {
                        $rawProps[$new] = $rawProps[$old];
                    }
                }

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

            $outputProperties = $mode === 'json' ? $properties : self::nestStructuredProperties($properties, $type);
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
     * (PostalAddress) and "geo" (GeoCoordinates) objects for JSON-LD output;
     * for events, the place name and address into "location" (Place).
     */
    private static function nestStructuredProperties(array $properties, string $type): array
    {
        $address = [];

        foreach (self::ADDRESS_KEYS as $key) {
            if (array_key_exists($key, $properties)) {
                $address[$key] = $properties[$key];
                unset($properties[$key]);
            }
        }

        $address = $address !== [] ? ['@type' => 'PostalAddress'] + $address : null;

        if (in_array($type, self::LOCATION_TYPES, true)) {
            $place = array_filter([
                'name' => $properties['locationName'] ?? null,
                'address' => $address,
            ]);
            unset($properties['locationName']);

            if ($place !== []) {
                $properties['location'] = ['@type' => 'Place'] + $place;
            }
        } elseif ($address !== null) {
            $properties['address'] = $address;
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

    /**
     * The pieces as stored: what the editor typed, without the resolved values.
     */
    public static function serialize(array $pieces): array
    {
        $stored = [];

        foreach ($pieces as $piece) {
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

        return $stored;
    }

    /**
     * The first problem that keeps the pieces from being saved, if any.
     */
    public static function error(array $pieces): ?string
    {
        foreach ($pieces as $piece) {
            if (!empty($piece['hasError'])) {
                return 'has a structured data row with invalid JSON properties.';
            }

            if (empty($piece['type'])) {
                return 'has a structured data row missing a schema.org type.';
            }
        }

        return null;
    }

    /**
     * The props of the pieces input (SchemaPiecesInput.vue).
     */
    public static function inputProps(string $name, array $pieces): array
    {
        $typeFields = [];

        foreach (array_keys(self::TYPE_PROPERTY_FIELDS) as $type) {
            $typeFields[$type] = self::basicFieldsForType($type);
        }

        return [
            'name' => $name,
            'rows' => array_map(static fn(array $piece) => [
                'type' => (string)($piece['type'] ?? ''),
                'mode' => $piece['mode'] ?? 'ui',
                'props' => (object)($piece['props'] ?? []),
                'propertiesJson' => (string)($piece['propertiesJson'] ?? ''),
                'hasError' => (bool)($piece['hasError'] ?? false),
            ], $pieces),
            'types' => self::CURATED_TYPES,
            'typeFields' => $typeFields,
            'defaultFields' => self::basicFieldsForType('__default__'),
        ];
    }

    /**
     * Each piece checked against schema.org and the Google rich result
     * profiles of its type, keyed like the pieces. Only the profiles of a
     * piece's own type are applied: an Article's publisher isn't held to
     * the Organization rules.
     *
     * @return array<int, array{errors: string[], warnings: string[], recommended: string[], profiles: string[]}>
     */
    public static function validate(array $pieces): array
    {
        self::$vocabulary ??= Vocabulary::default();
        self::$profiles ??= array_values(array_filter(
            Profiles::google(),
            static fn($profile) => $profile instanceof RuleProfile && !in_array($profile->id(), self::SKIPPED_PROFILES, true),
        ));

        $results = [];

        foreach ($pieces as $index => $piece) {
            $node = json_decode((string)($piece['json'] ?? ''), true);
            $type = (string)($piece['type'] ?? '');

            if (!is_array($node) || $type === '' || !empty($piece['hasError'])) {
                continue;
            }

            $profiles = array_values(array_filter(self::$profiles, static function(RuleProfile $profile) use ($type): bool {
                foreach ($profile->types() as $profileType) {
                    if ($type === $profileType || self::$vocabulary->isSubtypeOf($type, $profileType)) {
                        return true;
                    }
                }

                return false;
            }));
            $profiles = self::mostSpecific($profiles);

            $report = (new Validator(self::$vocabulary, ...$profiles))->validate(['@context' => 'https://schema.org'] + $node);
            $result = ['errors' => [], 'warnings' => [], 'recommended' => [], 'profiles' => array_map(static fn(RuleProfile $profile) => $profile->title(), $profiles)];

            foreach ($report->issues() as $issue) {
                // Many per type: listed together, by property
                if ($issue->code() === 'missing_recommended') {
                    $result['recommended'][] = (string)$issue->property();
                    continue;
                }

                $message = $issue->message() . ($issue->path() !== '' && $issue->property() === null ? ' (' . ltrim($issue->path(), '/') . ')' : '');
                $result[$issue->severity() === 'error' ? 'errors' : 'warnings'][] = $message;
            }

            $result['recommended'] = array_values(array_unique(array_filter($result['recommended'])));
            $results[$index] = $result;
        }

        return $results;
    }

    /**
     * Drops a profile when another one is for a subtype of its types: a
     * clinic gets the Local business rules, not the Organization ones too.
     *
     * @param RuleProfile[] $profiles
     * @return RuleProfile[]
     */
    private static function mostSpecific(array $profiles): array
    {
        return array_values(array_filter($profiles, static function(RuleProfile $profile) use ($profiles): bool {
            foreach ($profiles as $other) {
                if ($other === $profile) {
                    continue;
                }

                foreach ($other->types() as $otherType) {
                    foreach ($profile->types() as $type) {
                        if ($otherType !== $type && self::$vocabulary->isSubtypeOf($otherType, $type)) {
                            return false;
                        }
                    }
                }
            }

            return true;
        }));
    }
}
