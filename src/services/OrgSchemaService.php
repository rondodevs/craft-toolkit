<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\db\Query;
use craft\helpers\FileHelper;
use DateTime;
use DateTimeZone;
use yii\base\Component;
use yii\db\Schema;

/**
 * Stores the site-wide default schema.org identity per site, meant to feed
 * Nuxt SEO's schema-org module default identity (see
 * https://nuxtseo.com/docs/schema-org/guides/default-schema-org).
 */
class OrgSchemaService extends Component
{
    private const TABLE = '{{%toolkit_org_schema}}';

    public const TYPES = [
        'Organization',
        'Corporation',
        'NGO',
        'LocalBusiness',
        'ProfessionalService',
        'MedicalBusiness',
        'MedicalClinic',
        'EducationalOrganization',
        'Person',
    ];

    private const ADDRESS_KEYS = ['streetAddress', 'addressLocality', 'addressRegion', 'postalCode', 'addressCountry'];

    private const DAY_ORDER = ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'];

    /**
     * Per-type property availability, verified directly against schema.org
     * (fetched July 2026):
     *
     * - Organization (schema.org/Organization): "name", "legalName", "logo",
     *   "address", "telephone" etc. are defined directly on Organization —
     *   but NOT "openingHours"/"priceRange", and it does not inherit Place.
     * - Corporation, NGO (schema.org/Corporation, schema.org/NGO): plain
     *   Organization subtypes with no Place inheritance — same as
     *   Organization, no openingHours/priceRange.
     * - LocalBusiness (schema.org/LocalBusiness): defines "openingHours" and
     *   "priceRange" directly. It has *dual* inheritance from both
     *   Organization and Place (Thing > Organization > LocalBusiness AND
     *   Thing > Place > LocalBusiness).
     * - ProfessionalService, MedicalBusiness, MedicalClinic (schema.org/
     *   ProfessionalService, schema.org/MedicalBusiness,
     *   schema.org/MedicalClinic): LocalBusiness subtypes, so they inherit
     *   both "openingHours" and "priceRange". (ProfessionalService is marked
     *   deprecated upstream in favor of more specific types, e.g. Dentist,
     *   Attorney — kept here since it's still in wide use.)
     * - EducationalOrganization (schema.org/EducationalOrganization): inherits
     *   from CivicStructure (Thing > Place > CivicStructure >
     *   EducationalOrganization), which defines "openingHours" directly
     *   (schema.org/CivicStructure) — but CivicStructure/EducationalOrganization
     *   do NOT define "priceRange" anywhere in their hierarchy.
     * - Person (schema.org/Person): inherits only from Thing — no Place
     *   inheritance, so no "openingHours", "priceRange", "logo" or
     *   "legalName" (those aren't Person properties at all).
     */
    private const OPENING_HOURS_TYPES = ['LocalBusiness', 'ProfessionalService', 'MedicalBusiness', 'MedicalClinic', 'EducationalOrganization'];

    private const PRICE_RANGE_TYPES = ['LocalBusiness', 'ProfessionalService', 'MedicalBusiness', 'MedicalClinic'];

    /**
     * Which identity types each conditional CP field applies to. Fields not
     * listed here (name, url, description, email, telephone, addresses,
     * sameAs) are always shown, since they're valid across every type.
     */
    public const CONDITIONAL_FIELDS = [
        'legalName' => ['Organization', 'Corporation', 'NGO', 'LocalBusiness', 'ProfessionalService', 'MedicalBusiness', 'MedicalClinic', 'EducationalOrganization'],
        'logoUrl' => ['Organization', 'Corporation', 'NGO', 'LocalBusiness', 'ProfessionalService', 'MedicalBusiness', 'MedicalClinic', 'EducationalOrganization'],
        'priceRange' => self::PRICE_RANGE_TYPES,
        'openingHours' => self::OPENING_HOURS_TYPES,
    ];

    private function ensureDbTable(): bool
    {
        $db = Craft::$app->getDb();

        if ($db->tableExists(self::TABLE)) {
            return true;
        }

        try {
            $db->createCommand()->createTable(self::TABLE, [
                'id' => Schema::TYPE_PK,
                'siteHandle' => Schema::TYPE_STRING . ' NOT NULL',
                'dataJson' => Schema::TYPE_TEXT,
                'latestOrgSchemaUpdate' => Schema::TYPE_DATETIME,
                'dateCreated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'dateUpdated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'uid' => Schema::TYPE_CHAR . '(36) DEFAULT NULL',
            ])->execute();

            $db->createCommand()->createIndex('idx_toolkit_org_schema_uid', self::TABLE, 'uid', true)->execute();
            $db->createCommand()->createIndex('idx_toolkit_org_schema_site_handle', self::TABLE, 'siteHandle', true)->execute();

            return true;
        } catch (\Throwable $e) {
            Craft::warning('Toolkit Org Schema table could not be created automatically: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    private function hasDbTable(): bool
    {
        return $this->ensureDbTable();
    }

    private function getStoragePath(): string
    {
        return Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'toolkit' . DIRECTORY_SEPARATOR . 'org-schema.json';
    }

    public function getEnabledSites(): array
    {
        $sites = Craft::$app->getSites()->getAllSites();

        $sites = array_values(array_filter($sites, static function($site): bool {
            return !property_exists($site, 'enabled') || $site->enabled !== false;
        }));

        usort($sites, static fn($left, $right) => ($left->sortOrder ?? 0) <=> ($right->sortOrder ?? 0));

        return $sites;
    }

    private function getEnabledSiteMap(): array
    {
        $map = [];

        foreach ($this->getEnabledSites() as $site) {
            $map[$site->handle] = $site;
        }

        return $map;
    }

    private function defaultEntry(): array
    {
        return [
            'type' => 'Organization',
            'name' => '',
            'legalName' => '',
            'url' => '',
            'logoAssetId' => null,
            'description' => '',
            'email' => '',
            'telephone' => '',
            'sameAs' => [],
            'addresses' => [],
        ];
    }

    /**
     * Resolves a picked asset's URL for GraphQL/output, since the CP stores a
     * reference to the Asset element (via an element select input) rather
     * than a raw URL.
     */
    public static function resolveLogoUrl(mixed $logoAssetId): string
    {
        $id = is_numeric($logoAssetId) ? (int)$logoAssetId : null;

        if ($id === null || $id <= 0) {
            return '';
        }

        $asset = Craft::$app->getAssets()->getAssetById($id);

        return $asset?->getUrl() ?? '';
    }

    /**
     * Compiles a set of selected weekdays + open/close times into a
     * schema.org openingHours string, e.g. ['Mo','Tu','We'] + 09:00 + 18:00
     * => "Mo-We 09:00-18:00". Contiguous days are compressed into ranges.
     */
    public static function compileOpeningHours(array $days, string $opens, string $closes): string
    {
        $opens = trim($opens);
        $closes = trim($closes);
        $indices = [];

        foreach ($days as $day) {
            $index = array_search($day, self::DAY_ORDER, true);

            if ($index !== false) {
                $indices[$index] = true;
            }
        }

        $indices = array_keys($indices);
        sort($indices);

        if ($indices === [] || $opens === '' || $closes === '') {
            return '';
        }

        $ranges = [];
        $start = $indices[0];
        $prev = $indices[0];

        foreach (array_slice($indices, 1) as $index) {
            if ($index === $prev + 1) {
                $prev = $index;
                continue;
            }

            $ranges[] = [$start, $prev];
            $start = $index;
            $prev = $index;
        }

        $ranges[] = [$start, $prev];

        $dayParts = array_map(static function(array $range): string {
            [$start, $end] = $range;
            return $start === $end ? self::DAY_ORDER[$start] : self::DAY_ORDER[$start] . '-' . self::DAY_ORDER[$end];
        }, $ranges);

        return implode(',', $dayParts) . ' ' . $opens . '-' . $closes;
    }

    /**
     * Reverses compileOpeningHours() for redisplay in the CP picker.
     */
    public static function parseOpeningHoursString(string $value): array
    {
        $default = ['days' => [], 'opens' => '', 'closes' => ''];

        if (!preg_match('/^(.+?)\s+(\d{1,2}:\d{2})-(\d{1,2}:\d{2})$/', trim($value), $matches)) {
            return $default;
        }

        $days = [];

        foreach (explode(',', $matches[1]) as $part) {
            $part = trim($part);

            if (str_contains($part, '-')) {
                [$from, $to] = array_pad(explode('-', $part, 2), 2, '');
                $fromIndex = array_search($from, self::DAY_ORDER, true);
                $toIndex = array_search($to, self::DAY_ORDER, true);

                if ($fromIndex !== false && $toIndex !== false && $fromIndex <= $toIndex) {
                    for ($i = $fromIndex; $i <= $toIndex; $i++) {
                        $days[] = self::DAY_ORDER[$i];
                    }
                }
            } elseif (in_array($part, self::DAY_ORDER, true)) {
                $days[] = $part;
            }
        }

        return [
            'days' => array_values(array_unique($days)),
            'opens' => $matches[2],
            'closes' => $matches[3],
        ];
    }

    private function defaultAddress(): array
    {
        return [
            'streetAddress' => '',
            'addressLocality' => '',
            'addressRegion' => '',
            'postalCode' => '',
            'addressCountry' => '',
            // Opening hours and price range are properties of a physical place, not
            // of the sitewide identity — so with multiple addresses under one
            // MedicalClinic/LocalBusiness-like identity, each location gets its own.
            'priceRange' => '',
            'openingHours' => [],
        ];
    }

    private function normalizeAddress(array $raw): array
    {
        $address = $this->defaultAddress();

        foreach (self::ADDRESS_KEYS as $key) {
            $address[$key] = trim((string)($raw[$key] ?? ''));
        }

        $address['priceRange'] = trim((string)($raw['priceRange'] ?? ''));

        $rawOpeningHours = is_array($raw['openingHours'] ?? null) ? $raw['openingHours'] : [];
        $openingHours = [];

        foreach ($rawOpeningHours as $rawRow) {
            if (!is_array($rawRow)) {
                continue;
            }

            $days = is_array($rawRow['days'] ?? null) ? $rawRow['days'] : [];
            $compiled = self::compileOpeningHours($days, (string)($rawRow['opens'] ?? ''), (string)($rawRow['closes'] ?? ''));

            if ($compiled !== '') {
                $openingHours[] = $compiled;
            }
        }

        $address['openingHours'] = $openingHours;

        return $address;
    }

    private function isAddressEmpty(array $address): bool
    {
        foreach ($address as $value) {
            if (is_array($value)) {
                if ($value !== []) {
                    return false;
                }

                continue;
            }

            if ($value !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeEntry(array $raw): array
    {
        $entry = $this->defaultEntry();

        $type = trim((string)($raw['type'] ?? ''));
        $entry['type'] = in_array($type, self::TYPES, true) ? $type : 'Organization';

        foreach (['name', 'legalName', 'url', 'description', 'email', 'telephone'] as $key) {
            $entry[$key] = trim((string)($raw[$key] ?? ''));
        }

        $rawLogoAssetId = $raw['logoAssetId'] ?? null;
        $logoAssetId = is_array($rawLogoAssetId) ? ($rawLogoAssetId[0] ?? null) : $rawLogoAssetId;
        $entry['logoAssetId'] = is_numeric($logoAssetId) && (int)$logoAssetId > 0 ? (int)$logoAssetId : null;

        $sameAs = is_array($raw['sameAs'] ?? null) ? $raw['sameAs'] : [];
        $entry['sameAs'] = array_values(array_filter(array_map(static fn($url) => trim((string)$url), $sameAs), static fn($url) => $url !== ''));

        $rawAddresses = is_array($raw['addresses'] ?? null) ? $raw['addresses'] : [];
        $addresses = [];

        foreach ($rawAddresses as $rawAddress) {
            if (!is_array($rawAddress)) {
                continue;
            }

            $address = $this->normalizeAddress($rawAddress);

            if (!$this->isAddressEmpty($address)) {
                $addresses[] = $address;
            }
        }

        $entry['addresses'] = $addresses;

        return $entry;
    }

    private function isEntryEmpty(array $entry): bool
    {
        if ($entry['name'] !== '' || $entry['legalName'] !== '' || $entry['url'] !== '' || $entry['logoAssetId'] !== null || $entry['description'] !== '' || $entry['email'] !== '' || $entry['telephone'] !== '') {
            return false;
        }

        return $entry['sameAs'] === [] && $entry['addresses'] === [];
    }

    private function normalizeStoredOverrides(array $payload): array
    {
        $normalized = [];
        $siteMap = $this->getEnabledSiteMap();
        $sitesPayload = $payload['sites'] ?? $payload;

        foreach ($siteMap as $siteHandle => $site) {
            $sitePayload = is_array($sitesPayload[$siteHandle] ?? null) ? $sitesPayload[$siteHandle] : [];
            $normalized[$siteHandle] = [
                'entry' => $this->normalizeEntry(is_array($sitePayload['entry'] ?? null) ? $sitePayload['entry'] : $sitePayload),
                'latestOrgSchemaUpdate' => $sitePayload['latestOrgSchemaUpdate'] ?? null,
            ];
        }

        return $normalized;
    }

    private function getFileOverrides(): array
    {
        $path = $this->getStoragePath();

        if (!file_exists($path)) {
            return $this->normalizeStoredOverrides([]);
        }

        $payload = json_decode(file_get_contents($path), true);

        if (!is_array($payload)) {
            return $this->normalizeStoredOverrides([]);
        }

        return $this->normalizeStoredOverrides($payload);
    }

    public function getOverrides(): array
    {
        if (!$this->hasDbTable()) {
            return $this->getFileOverrides();
        }

        $rows = (new Query())
            ->from(self::TABLE)
            ->all();

        if ($rows === []) {
            return $this->getFileOverrides();
        }

        $payload = ['sites' => []];

        foreach ($rows as $row) {
            $payload['sites'][$row['siteHandle']] = [
                'entry' => json_decode((string)($row['dataJson'] ?? '{}'), true),
                'latestOrgSchemaUpdate' => $row['latestOrgSchemaUpdate'] ?? null,
            ];
        }

        return $this->normalizeStoredOverrides($payload);
    }

    public function getResolvedOrgSchema(): array
    {
        $overrides = $this->getOverrides();
        $sites = [];

        foreach ($this->getEnabledSites() as $site) {
            $siteHandle = $site->handle;
            $siteOverride = $overrides[$siteHandle] ?? ['entry' => $this->defaultEntry(), 'latestOrgSchemaUpdate' => null];
            $entry = $siteOverride['entry'];
            $sites[] = array_merge(
                ['siteHandle' => $siteHandle, 'siteName' => $site->name],
                $entry,
                [
                    'logoUrl' => self::resolveLogoUrl($entry['logoAssetId'] ?? null),
                    'latestOrgSchemaUpdate' => $siteOverride['latestOrgSchemaUpdate'],
                ]
            );
        }

        return ['sites' => $sites];
    }

    private function resolveSiteHandle(string $siteHandle): string
    {
        $siteHandle = trim($siteHandle);
        $siteMap = $this->getEnabledSiteMap();

        if ($siteHandle !== '' && isset($siteMap[$siteHandle])) {
            return $siteHandle;
        }

        throw new \InvalidArgumentException('A valid site handle is required for org schema.');
    }

    public function getResolvedForSite(string $siteHandle): array
    {
        $resolvedHandle = $this->resolveSiteHandle($siteHandle);
        $siteMap = $this->getEnabledSiteMap();
        $overrides = $this->getOverrides();
        $site = $siteMap[$resolvedHandle];
        $siteOverride = $overrides[$resolvedHandle] ?? ['entry' => $this->defaultEntry(), 'latestOrgSchemaUpdate' => null];
        $entry = $siteOverride['entry'];

        return array_merge(
            ['siteHandle' => $site->handle, 'siteName' => $site->name],
            $entry,
            [
                'logoUrl' => self::resolveLogoUrl($entry['logoAssetId'] ?? null),
                'latestOrgSchemaUpdate' => $siteOverride['latestOrgSchemaUpdate'],
            ]
        );
    }

    public function saveOverrides(array $entriesBySite): array
    {
        $siteMap = $this->getEnabledSiteMap();
        $timestamp = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
        $payload = [];

        foreach ($siteMap as $siteHandle => $site) {
            $raw = is_array($entriesBySite[$siteHandle] ?? null) ? $entriesBySite[$siteHandle] : [];
            $entry = $this->normalizeEntry($raw);
            $isEmpty = $this->isEntryEmpty($entry);

            $payload[$siteHandle] = [
                'entry' => $entry,
                'latestOrgSchemaUpdate' => $isEmpty ? null : $timestamp,
                'isEmpty' => $isEmpty,
            ];
        }

        if ($this->hasDbTable()) {
            $db = Craft::$app->getDb();

            foreach ($payload as $siteHandle => $sitePayload) {
                $exists = (new Query())
                    ->from(self::TABLE)
                    ->where(['siteHandle' => $siteHandle])
                    ->exists();

                if ($sitePayload['isEmpty']) {
                    if ($exists) {
                        $db->createCommand()
                            ->delete(self::TABLE, ['siteHandle' => $siteHandle])
                            ->execute();
                    }

                    continue;
                }

                $rowPayload = [
                    'siteHandle' => $siteHandle,
                    'dataJson' => json_encode($sitePayload['entry'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'latestOrgSchemaUpdate' => $sitePayload['latestOrgSchemaUpdate'],
                ];

                if ($exists) {
                    $db->createCommand()
                        ->update(self::TABLE, $rowPayload, ['siteHandle' => $siteHandle])
                        ->execute();
                } else {
                    $db->createCommand()
                        ->insert(self::TABLE, $rowPayload)
                        ->execute();
                }
            }

            return $this->getResolvedOrgSchema();
        }

        $filePayload = ['sites' => []];

        foreach ($payload as $siteHandle => $sitePayload) {
            if ($sitePayload['isEmpty']) {
                continue;
            }

            $filePayload['sites'][$siteHandle] = [
                'entry' => $sitePayload['entry'],
                'latestOrgSchemaUpdate' => $sitePayload['latestOrgSchemaUpdate'],
            ];
        }

        $path = $this->getStoragePath();
        FileHelper::createDirectory(dirname($path));
        file_put_contents($path, json_encode($filePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $this->getResolvedOrgSchema();
    }
}
