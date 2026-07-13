<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\db\Query;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Color;
use craft\helpers\FileHelper;
use craft\models\FieldLayoutTab;
use craft\models\Volume;
use yii\base\Component;
use yii\db\Schema;

class AverageColorService extends Component
{
    private const TABLE = '{{%toolkit_average_color_settings}}';
    private const DEFAULT_VOLUME_HANDLE = 'media';

    private function ensureDbTable(): bool
    {
        $db = Craft::$app->getDb();

        if ($db->tableExists(self::TABLE)) {
            return true;
        }

        try {
            $db->createCommand()->createTable(self::TABLE, [
                'id' => Schema::TYPE_PK,
                'enabled' => Schema::TYPE_BOOLEAN,
                'volumeIds' => Schema::TYPE_TEXT,
                'dateCreated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'dateUpdated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'uid' => Schema::TYPE_CHAR . '(36) DEFAULT NULL',
            ])->execute();

            $db->createCommand()->createIndex('idx_toolkit_average_color_settings_uid', self::TABLE, 'uid', true)->execute();

            return true;
        } catch (\Throwable $e) {
            Craft::warning('Toolkit average color settings table could not be created automatically: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    private function getStoragePath(): string
    {
        return Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'toolkit' . DIRECTORY_SEPARATOR . 'average-color-settings.json';
    }

    private function hasDbTable(): bool
    {
        return $this->ensureDbTable();
    }

    private function defaultVolumeIds(): array
    {
        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            if ($volume->handle === self::DEFAULT_VOLUME_HANDLE) {
                return [$volume->id];
            }
        }

        return [];
    }

    public function getDefaultSettings(): array
    {
        return [
            'enabled' => true,
            'volumeIds' => $this->defaultVolumeIds(),
        ];
    }

    private function normalizeSettings(array $settings): array
    {
        return [
            'enabled' => array_key_exists('enabled', $settings) ? (bool)$settings['enabled'] : null,
            'volumeIds' => $this->normalizeVolumeIds($settings['volumeIds'] ?? null),
        ];
    }

    private function normalizeVolumeIds(mixed $value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($value)) {
            return null;
        }

        return array_values(array_unique(array_filter(array_map('intval', $value))));
    }

    private function getFileOverrides(): array
    {
        $path = $this->getStoragePath();

        if (!file_exists($path)) {
            return $this->normalizeSettings([]);
        }

        $settings = json_decode(file_get_contents($path), true);

        if (!is_array($settings)) {
            return $this->normalizeSettings([]);
        }

        return $this->normalizeSettings($settings);
    }

    public function getOverrides(): array
    {
        if ($this->hasDbTable()) {
            $row = (new Query())
                ->from(self::TABLE)
                ->orderBy(['id' => SORT_ASC])
                ->one();

            if (is_array($row)) {
                return $this->normalizeSettings([
                    'enabled' => $row['enabled'] ?? null,
                    'volumeIds' => $row['volumeIds'] ?? null,
                ]);
            }

            return $this->getFileOverrides();
        }

        return $this->getFileOverrides();
    }

    public function getResolvedSettings(): array
    {
        $defaults = $this->getDefaultSettings();
        $overrides = $this->getOverrides();

        return [
            'enabled' => $overrides['enabled'] ?? $defaults['enabled'],
            'volumeIds' => $overrides['volumeIds'] ?? $defaults['volumeIds'],
        ];
    }

    public function isEnabled(): bool
    {
        return (bool)$this->getResolvedSettings()['enabled'];
    }

    public function isVolumeSelected(?int $volumeId): bool
    {
        if ($volumeId === null) {
            return false;
        }

        return in_array($volumeId, $this->getResolvedSettings()['volumeIds'], true);
    }

    public function saveSettings(array $settings): array
    {
        $enabled = (bool)($settings['enabled'] ?? false);
        $volumeIds = $this->normalizeVolumeIds($settings['volumeIds'] ?? []) ?? [];

        $payload = [
            'enabled' => $enabled,
            'volumeIds' => json_encode($volumeIds),
        ];

        if ($this->hasDbTable()) {
            $db = Craft::$app->getDb();
            $exists = (new Query())
                ->from(self::TABLE)
                ->exists();

            if ($exists) {
                $db->createCommand()
                    ->update(self::TABLE, $payload)
                    ->execute();
            } else {
                $db->createCommand()
                    ->insert(self::TABLE, $payload)
                    ->execute();
            }

            return $this->getResolvedSettings();
        }

        $path = $this->getStoragePath();
        FileHelper::createDirectory(dirname($path));
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $this->getResolvedSettings();
    }

    /**
     * @return array<int, array{id:int, handle:string, name:string, selected:bool, hasAverageColorField:bool}>
     */
    public function getVolumeStatuses(): array
    {
        $selectedIds = $this->getResolvedSettings()['volumeIds'];
        $statuses = [];

        foreach (Craft::$app->getVolumes()->getAllVolumes() as $volume) {
            $statuses[] = [
                'id' => $volume->id,
                'handle' => $volume->handle,
                'name' => $volume->name,
                'selected' => in_array($volume->id, $selectedIds, true),
                'hasAverageColorField' => $this->volumeHasAverageColorField($volume),
            ];
        }

        return $statuses;
    }

    private function volumeHasAverageColorField(Volume $volume): bool
    {
        $fieldLayout = $volume->getFieldLayout();

        if ($fieldLayout === null) {
            return false;
        }

        foreach ($fieldLayout->getCustomFields() as $field) {
            if ($field->handle === 'averageColor') {
                return true;
            }
        }

        return false;
    }

    public function ensureFieldOnVolume(int $volumeId): array
    {
        if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
            return [
                'success' => false,
                'message' => 'Admin changes are disabled in this environment (allowAdminChanges is false), so the field cannot be created here.',
            ];
        }

        $volume = Craft::$app->getVolumes()->getVolumeById($volumeId);

        if ($volume === null) {
            return [
                'success' => false,
                'message' => 'Volume not found.',
            ];
        }

        if ($this->volumeHasAverageColorField($volume)) {
            return [
                'success' => true,
                'message' => 'The averageColor field already exists on this volume.',
            ];
        }

        try {
            $fieldsService = Craft::$app->getFields();
            $field = $fieldsService->getFieldByHandle('averageColor');

            if ($field === null) {
                $field = new Color([
                    'name' => 'Average Color',
                    'handle' => 'averageColor',
                    'allowCustomColors' => true,
                ]);

                if (!$fieldsService->saveField($field)) {
                    return [
                        'success' => false,
                        'message' => 'Unable to create the averageColor field: ' . implode(' ', $field->getErrorSummary(true)),
                    ];
                }
            }

            $fieldLayout = $volume->getFieldLayout();
            $tabs = $fieldLayout->getTabs();

            if (empty($tabs)) {
                $tab = new FieldLayoutTab(['name' => 'Content']);
                $tab->setLayout($fieldLayout);
                $tabs = [$tab];
            }

            $firstTab = $tabs[0];
            $elements = $firstTab->getElements();
            $elements[] = new CustomField($field);
            $firstTab->setElements($elements);
            $fieldLayout->setTabs($tabs);
            $volume->setFieldLayout($fieldLayout);

            if (!Craft::$app->getVolumes()->saveVolume($volume)) {
                return [
                    'success' => false,
                    'message' => 'Unable to save the volume: ' . implode(' ', $volume->getErrorSummary(true)),
                ];
            }
        } catch (\Throwable $e) {
            Craft::error('Toolkit average color field creation failed: ' . $e->getMessage(), __METHOD__);

            return [
                'success' => false,
                'message' => 'Unable to create the averageColor field: ' . $e->getMessage(),
            ];
        }

        return [
            'success' => true,
            'message' => 'The averageColor field was added to this volume.',
        ];
    }
}
