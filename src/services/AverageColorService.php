<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Color;
use craft\models\FieldLayoutTab;
use craft\models\Volume;
use rondodevs\toolkit\support\Normalize;
use rondodevs\toolkit\support\SettingsTable;
use yii\base\Component;
use yii\db\Schema;

class AverageColorService extends Component
{
    private const DEFAULT_VOLUME_HANDLE = 'media';

    private ?SettingsTable $table = null;

    private function table(): SettingsTable
    {
        return $this->table ??= new SettingsTable('{{%toolkit_average_color_settings}}', [
            'enabled' => Schema::TYPE_BOOLEAN,
            'volumeIds' => Schema::TYPE_TEXT,
        ], 'average-color-settings.json');
    }

    public function getDefaultSettings(): array
    {
        $volume = Craft::$app->getVolumes()->getVolumeByHandle(self::DEFAULT_VOLUME_HANDLE);

        return [
            'enabled' => true,
            'volumeIds' => $volume ? [$volume->id] : [],
        ];
    }

    public function getOverrides(): array
    {
        $row = $this->table()->first() ?? [];

        return [
            'enabled' => Normalize::bool($row['enabled'] ?? null),
            'volumeIds' => $this->normalizeVolumeIds($row['volumeIds'] ?? null),
        ];
    }

    /**
     * Stored as a JSON string in the table, as an array in the legacy file.
     */
    private function normalizeVolumeIds(mixed $value): ?array
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (!is_array($value)) {
            return null;
        }

        return array_values(array_unique(array_filter(array_map('intval', $value))));
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
        return $volumeId !== null && in_array($volumeId, $this->getResolvedSettings()['volumeIds'], true);
    }

    public function saveSettings(array $settings): array
    {
        $this->table()->put([
            'enabled' => (bool)($settings['enabled'] ?? false),
            'volumeIds' => json_encode($this->normalizeVolumeIds($settings['volumeIds'] ?? []) ?? []),
        ]);

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
