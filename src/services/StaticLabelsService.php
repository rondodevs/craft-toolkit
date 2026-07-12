<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\db\Query;
use craft\helpers\FileHelper;
use DateTime;
use DateTimeZone;
use yii\base\Component;
use yii\db\Schema;

class StaticLabelsService extends Component
{
    private const TABLE = '{{%toolkit_static_labels}}';

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
                'labelsJson' => Schema::TYPE_TEXT,
                'latestStaticLabelsUpdate' => Schema::TYPE_DATETIME,
                'dateCreated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'dateUpdated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'uid' => Schema::TYPE_CHAR . '(36) DEFAULT NULL',
            ])->execute();

            $db->createCommand()->createIndex('idx_toolkit_static_labels_uid', self::TABLE, 'uid', true)->execute();
            $db->createCommand()->createIndex('idx_toolkit_static_labels_site_handle', self::TABLE, 'siteHandle', true)->execute();

            return true;
        } catch (\Throwable $e) {
            Craft::warning('Toolkit Static Labels table could not be created automatically: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    private function hasDbTable(): bool
    {
        return $this->ensureDbTable();
    }

    private function getStoragePath(): string
    {
        return Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'toolkit' . DIRECTORY_SEPARATOR . 'static-labels.json';
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

    private function normalizeLabelRows(array $rows): array
    {
        $normalized = [];

        foreach ($rows as $row) {
            $key = preg_replace('/\s+/', '', trim((string)($row['key'] ?? '')));

            if ($key === '') {
                continue;
            }

            $singleValue = trim((string)($row['singleValue'] ?? $row['value'] ?? ''));
            $zeroValue = trim((string)($row['zeroValue'] ?? ''));
            $manyValue = trim((string)($row['manyValue'] ?? ''));
            $mode = trim((string)($row['mode'] ?? ''));

            if ($mode !== 'plural' && $mode !== 'single') {
                $mode = ($zeroValue !== '' || $manyValue !== '') ? 'plural' : 'single';
            }

            $normalized[] = [
                'key' => $key,
                'mode' => $mode,
                'value' => $singleValue,
                'singleValue' => $singleValue,
                'zeroValue' => $zeroValue,
                'manyValue' => $manyValue,
            ];
        }

        return $normalized;
    }

    private function normalizeInputLabels(array $labelsBySite): array
    {
        $normalized = [];
        $siteMap = $this->getEnabledSiteMap();

        foreach ($siteMap as $siteHandle => $site) {
            $siteValues = $labelsBySite[$siteHandle] ?? [];
            $keys = is_array($siteValues['keys'] ?? null) ? $siteValues['keys'] : [];
            $modes = is_array($siteValues['modes'] ?? null) ? $siteValues['modes'] : [];
            $singleValues = is_array($siteValues['singleValues'] ?? null) ? $siteValues['singleValues'] : [];
            $oneValues = is_array($siteValues['oneValues'] ?? null) ? $siteValues['oneValues'] : [];
            $zeroValues = is_array($siteValues['zeroValues'] ?? null) ? $siteValues['zeroValues'] : [];
            $manyValues = is_array($siteValues['manyValues'] ?? null) ? $siteValues['manyValues'] : [];
            $legacyValues = is_array($siteValues['values'] ?? null) ? $siteValues['values'] : [];
            $rows = [];
            $rowCount = max(count($keys), count($modes), count($singleValues), count($oneValues), count($zeroValues), count($manyValues), count($legacyValues));

            for ($index = 0; $index < $rowCount; $index++) {
                $mode = $modes[$index] ?? null;
                $mode = $mode === 'plural' ? 'plural' : 'single';

                $rows[] = [
                    'key' => $keys[$index] ?? null,
                    'mode' => $mode,
                    'singleValue' => $mode === 'plural'
                        ? ($oneValues[$index] ?? ($singleValues[$index] ?? ($legacyValues[$index] ?? null)))
                        : ($singleValues[$index] ?? ($oneValues[$index] ?? ($legacyValues[$index] ?? null))),
                    'zeroValue' => $zeroValues[$index] ?? null,
                    'manyValue' => $manyValues[$index] ?? null,
                ];
            }

            $normalized[$siteHandle] = [
                'labels' => $this->normalizeLabelRows($rows),
                'latestStaticLabelsUpdate' => null,
            ];
        }

        return $this->synchronizeLabelsAcrossSites($normalized);
    }

    private function synchronizeLabelsAcrossSites(array $labelsBySite): array
    {
        $allKeys = [];
        $seedRowsByKey = [];

        foreach ($labelsBySite as $siteHandle => $sitePayload) {
            $labels = is_array($sitePayload['labels'] ?? null) ? $sitePayload['labels'] : [];

            foreach ($labels as $label) {
                $key = trim((string)($label['key'] ?? ''));

                if ($key === '') {
                    continue;
                }

                if (!in_array($key, $allKeys, true)) {
                    $allKeys[] = $key;
                }

                if (!array_key_exists($key, $seedRowsByKey)) {
                    $seedRowsByKey[$key] = [
                        'key' => $key,
                        'mode' => (string)($label['mode'] ?? 'single'),
                        'value' => trim((string)($label['value'] ?? '')),
                        'singleValue' => trim((string)($label['singleValue'] ?? $label['value'] ?? '')),
                        'zeroValue' => trim((string)($label['zeroValue'] ?? '')),
                        'manyValue' => trim((string)($label['manyValue'] ?? '')),
                    ];
                }
            }
        }

        foreach ($labelsBySite as $siteHandle => &$sitePayload) {
            $labels = is_array($sitePayload['labels'] ?? null) ? $sitePayload['labels'] : [];
            $labelsByKey = [];

            foreach ($labels as $label) {
                $key = trim((string)($label['key'] ?? ''));

                if ($key === '') {
                    continue;
                }

                $labelsByKey[$key] = [
                    'key' => $key,
                    'mode' => (string)($label['mode'] ?? 'single'),
                    'value' => trim((string)($label['value'] ?? '')),
                    'singleValue' => trim((string)($label['singleValue'] ?? $label['value'] ?? '')),
                    'zeroValue' => trim((string)($label['zeroValue'] ?? '')),
                    'manyValue' => trim((string)($label['manyValue'] ?? '')),
                ];
            }

            $synchronized = [];

            foreach ($allKeys as $key) {
                $synchronized[] = $labelsByKey[$key] ?? ($seedRowsByKey[$key] ?? [
                    'key' => $key,
                    'mode' => 'single',
                    'value' => '',
                    'singleValue' => '',
                    'zeroValue' => '',
                    'manyValue' => '',
                ]);
            }

            $sitePayload['labels'] = $synchronized;
        }
        unset($sitePayload);

        return $labelsBySite;
    }

    private function normalizeStoredOverrides(array $payload): array
    {
        $normalized = [];
        $siteMap = $this->getEnabledSiteMap();
        $sitesPayload = $payload['sites'] ?? $payload;

        foreach ($siteMap as $siteHandle => $site) {
            $sitePayload = $sitesPayload[$siteHandle] ?? [];
            $normalized[$siteHandle] = [
                'labels' => $this->normalizeLabelRows(is_array($sitePayload['labels'] ?? null) ? $sitePayload['labels'] : []),
                'latestStaticLabelsUpdate' => $sitePayload['latestStaticLabelsUpdate'] ?? null,
            ];
        }

        return $this->synchronizeLabelsAcrossSites($normalized);
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
                'labels' => json_decode((string)($row['labelsJson'] ?? '[]'), true),
                'latestStaticLabelsUpdate' => $row['latestStaticLabelsUpdate'] ?? null,
            ];
        }

        return $this->normalizeStoredOverrides($payload);
    }

    public function getResolvedStaticLabels(): array
    {
        $overrides = $this->getOverrides();
        $sites = [];

        foreach ($this->getEnabledSites() as $site) {
            $siteHandle = $site->handle;
            $siteOverride = $overrides[$siteHandle] ?? ['labels' => [], 'latestStaticLabelsUpdate' => null];
            $sites[] = [
                'siteHandle' => $siteHandle,
                'siteName' => $site->name,
                'labels' => $siteOverride['labels'],
                'latestStaticLabelsUpdate' => $siteOverride['latestStaticLabelsUpdate'],
            ];
        }

        return [
            'sites' => $sites,
        ];
    }

    private function resolveSiteHandle(string $siteHandle): string
    {
        $siteHandle = trim((string)$siteHandle);
        $siteMap = $this->getEnabledSiteMap();

        if ($siteHandle !== '' && isset($siteMap[$siteHandle])) {
            return $siteHandle;
        }

        throw new \InvalidArgumentException('A valid site handle is required for static labels.');
    }

    public function getResolvedLabelsForSite(string $siteHandle): array
    {
        $resolvedHandle = $this->resolveSiteHandle($siteHandle);
        $siteMap = $this->getEnabledSiteMap();
        $overrides = $this->getOverrides();
        $site = $siteMap[$resolvedHandle];
        $siteOverride = $overrides[$resolvedHandle] ?? ['labels' => [], 'latestStaticLabelsUpdate' => null];

        return [
            'siteHandle' => $site->handle,
            'siteName' => $site->name,
            'labels' => $siteOverride['labels'],
            'latestStaticLabelsUpdate' => $siteOverride['latestStaticLabelsUpdate'],
        ];
    }

    public function saveOverrides(array $labelsBySite): array
    {
        $payload = $this->normalizeInputLabels($labelsBySite);
        $timestamp = (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s');

        foreach ($payload as $siteHandle => &$sitePayload) {
            $sitePayload['latestStaticLabelsUpdate'] = $sitePayload['labels'] === [] ? null : $timestamp;
        }
        unset($sitePayload);

        if ($this->hasDbTable()) {
            $db = Craft::$app->getDb();

            foreach ($payload as $siteHandle => $sitePayload) {
                $exists = (new Query())
                    ->from(self::TABLE)
                    ->where(['siteHandle' => $siteHandle])
                    ->exists();

                if ($sitePayload['labels'] === []) {
                    if ($exists) {
                        $db->createCommand()
                            ->delete(self::TABLE, ['siteHandle' => $siteHandle])
                            ->execute();
                    }

                    continue;
                }

                $rowPayload = [
                    'siteHandle' => $siteHandle,
                    'labelsJson' => json_encode($sitePayload['labels'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'latestStaticLabelsUpdate' => $sitePayload['latestStaticLabelsUpdate'],
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

            return $this->getResolvedStaticLabels();
        }

        $filePayload = ['sites' => []];

        foreach ($payload as $siteHandle => $sitePayload) {
            if ($sitePayload['labels'] === []) {
                continue;
            }

            $filePayload['sites'][$siteHandle] = $sitePayload;
        }

        $path = $this->getStoragePath();
        FileHelper::createDirectory(dirname($path));
        file_put_contents($path, json_encode($filePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $this->getResolvedStaticLabels();
    }
}