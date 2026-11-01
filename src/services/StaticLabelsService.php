<?php

namespace rondodevs\toolkit\services;

use rondodevs\toolkit\support\SettingsTable;
use rondodevs\toolkit\support\Sites;
use yii\base\Component;
use yii\db\Schema;

/**
 * Translation labels per site, edited in the CP and read by the frontend through GraphQL.
 * Every site has the same keys: a key added on one site is added, empty, to the others.
 */
class StaticLabelsService extends Component
{
    private ?SettingsTable $table = null;

    private function table(): SettingsTable
    {
        return $this->table ??= new SettingsTable('{{%toolkit_static_labels}}', [
            'siteHandle' => Schema::TYPE_STRING . ' NOT NULL',
            'labelsJson' => Schema::TYPE_TEXT,
            'latestStaticLabelsUpdate' => Schema::TYPE_DATETIME,
        ], 'static-labels.json', ['siteHandle' => 'idx_toolkit_static_labels_site_handle']);
    }

    /**
     * A label as stored and exposed: `value` repeats `singleValue` for older frontends.
     */
    private function normalizeLabel(array $row): ?array
    {
        $key = preg_replace('/\s+/', '', trim((string)($row['key'] ?? '')));

        if ($key === '') {
            return null;
        }

        $singleValue = trim((string)($row['singleValue'] ?? $row['value'] ?? ''));
        $zeroValue = trim((string)($row['zeroValue'] ?? ''));
        $manyValue = trim((string)($row['manyValue'] ?? ''));
        $mode = $row['mode'] ?? null;

        if ($mode !== 'plural' && $mode !== 'single') {
            $mode = ($zeroValue !== '' || $manyValue !== '') ? 'plural' : 'single';
        }

        return [
            'key' => $key,
            'mode' => $mode,
            'value' => $singleValue,
            'singleValue' => $singleValue,
            'zeroValue' => $zeroValue,
            'manyValue' => $manyValue,
        ];
    }

    /**
     * @param array<string, array> $labelsBySite Site handle => label rows
     * @return array<string, array> Site handle => labels, every enabled site, the same keys on each
     */
    private function normalizeLabels(array $labelsBySite): array
    {
        $bySite = [];
        $seeds = [];

        foreach (Sites::enabled() as $handle => $site) {
            $bySite[$handle] = [];

            foreach ((array)($labelsBySite[$handle] ?? []) as $row) {
                $label = is_array($row) ? $this->normalizeLabel($row) : null;

                if ($label !== null) {
                    $bySite[$handle][$label['key']] = $label;
                    // A key missing on another site starts from the first site's label
                    $seeds[$label['key']] ??= $label;
                }
            }
        }

        return array_map(
            static fn(array $labels) => array_values(array_map(static fn(array $seed) => $labels[$seed['key']] ?? $seed, $seeds)),
            $bySite,
        );
    }

    /**
     * @return array<string, array{labels: array, latestStaticLabelsUpdate: ?string}>
     */
    public function getOverrides(): array
    {
        $rows = $this->table()->rows();
        $stored = [];

        if ($rows !== []) {
            foreach ($rows as $row) {
                $stored[$row['siteHandle']] = [
                    'labels' => json_decode((string)($row['labelsJson'] ?? '[]'), true) ?: [],
                    'latestStaticLabelsUpdate' => $row['latestStaticLabelsUpdate'] ?? null,
                ];
            }
        } else {
            $file = $this->table()->readFile() ?? [];
            $stored = $file['sites'] ?? $file;
        }

        $labels = $this->normalizeLabels(array_map(static fn($site) => $site['labels'] ?? [], $stored));

        return array_map(static fn(string $handle) => [
            'labels' => $labels[$handle],
            'latestStaticLabelsUpdate' => $stored[$handle]['latestStaticLabelsUpdate'] ?? null,
        ], array_combine(array_keys($labels), array_keys($labels)));
    }

    public function getResolvedStaticLabels(): array
    {
        $overrides = $this->getOverrides();

        return [
            'sites' => array_values(array_map(static fn($site) => [
                'siteHandle' => $site->handle,
                'siteName' => $site->name,
                ...$overrides[$site->handle],
            ], Sites::enabled())),
        ];
    }

    public function getResolvedLabelsForSite(string $siteHandle): array
    {
        $site = Sites::requireEnabled($siteHandle, 'static labels');

        return [
            'siteHandle' => $site->handle,
            'siteName' => $site->name,
            ...$this->getOverrides()[$site->handle],
        ];
    }

    /**
     * @param array<string, array> $labelsBySite Site handle => rows of key, mode, singleValue, zeroValue, manyValue
     */
    public function saveOverrides(array $labelsBySite): array
    {
        $labels = $this->normalizeLabels($labelsBySite);
        $timestamp = SettingsTable::now();

        if (!$this->table()->isAvailable()) {
            $this->table()->writeFile(['sites' => array_map(static fn(array $siteLabels) => [
                'labels' => $siteLabels,
                'latestStaticLabelsUpdate' => $timestamp,
            ], array_filter($labels))]);

            return $this->getResolvedStaticLabels();
        }

        foreach ($labels as $handle => $siteLabels) {
            if ($siteLabels === []) {
                $this->table()->delete(['siteHandle' => $handle]);
                continue;
            }

            $this->table()->save([
                'labelsJson' => json_encode($siteLabels, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'latestStaticLabelsUpdate' => $timestamp,
            ], ['siteHandle' => $handle]);
        }

        return $this->getResolvedStaticLabels();
    }
}
