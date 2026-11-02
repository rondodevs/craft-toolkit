<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\elements\Asset;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Color;
use craft\models\FieldLayoutTab;
use craft\models\Volume;
use rondodevs\toolkit\support\Normalize;
use rondodevs\toolkit\support\SettingsTable;
use Throwable;
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

    /**
     * Whether the asset gets an average color: switched on, its volume
     * selected and an `averageColor` field in its layout.
     */
    public function appliesTo(Asset $asset): bool
    {
        return $this->isEnabled() && $this->isVolumeSelected($asset->volumeId) && $this->hasAverageColorField($asset);
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

    private function hasAverageColorField(Asset $asset): bool
    {
        $fieldLayout = $asset->getFieldLayout();

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

    /**
     * The image's average color as `#RRGGBB`, or null when it can't be read.
     */
    public function calculate(Asset $asset): ?string
    {
        $temporaryPath = null;

        try {
            $imagePath = $asset->tempFilePath ?? $asset->getImageTransformSourcePath();

            if (!is_file($imagePath)) {
                $temporaryPath = $asset->getCopyOfFile();
                $imagePath = $temporaryPath;
            }

            if (!is_file($imagePath)) {
                return null;
            }

            $averageColor = $this->calculateWithGd($imagePath);

            if ($averageColor !== null) {
                return $averageColor;
            }

            return $this->calculateWithImagick($imagePath);
        } catch (Throwable) {
            return null;
        } finally {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private function calculateWithGd(string $imagePath): ?string
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagecopyresampled')) {
            return null;
        }

        $imageContents = @file_get_contents($imagePath);

        if ($imageContents === false) {
            return null;
        }

        $image = @imagecreatefromstring($imageContents);

        if ($image === false) {
            return null;
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $averageImage = imagecreatetruecolor(1, 1);

        if ($width < 1 || $height < 1 || $averageImage === false) {
            return null;
        }

        imagealphablending($averageImage, true);
        imagesavealpha($averageImage, true);

        $copied = imagecopyresampled($averageImage, $image, 0, 0, 0, 0, 1, 1, $width, $height);

        if ($copied === false) {
            return null;
        }

        $rgb = imagecolorat($averageImage, 0, 0);
        $red = ($rgb >> 16) & 0xFF;
        $green = ($rgb >> 8) & 0xFF;
        $blue = $rgb & 0xFF;

        return sprintf('#%02X%02X%02X', $red, $green, $blue);
    }

    private function calculateWithImagick(string $imagePath): ?string
    {
        $imagickClass = 'Imagick';

        if (!class_exists($imagickClass)) {
            return null;
        }

        $imagick = null;

        try {
            $imagick = new $imagickClass($imagePath);
            $imagick->setIteratorIndex(0);
            $imagick->thumbnailImage(1, 1, true, true);

            $color = $imagick->getImagePixelColor(0, 0)->getColor();

            return sprintf('#%02X%02X%02X', (int)$color['r'], (int)$color['g'], (int)$color['b']);
        } catch (Throwable) {
            return null;
        } finally {
            if (is_object($imagick) && method_exists($imagick, 'clear') && method_exists($imagick, 'destroy')) {
                $imagick->clear();
                $imagick->destroy();
            }
        }
    }
}
