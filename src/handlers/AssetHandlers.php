<?php

namespace rondodevs\toolkit\handlers;

use Craft;
use craft\elements\Asset;
use craft\events\ModelEvent;
use Throwable;
use yii\base\Event;

class AssetHandlers
{
    public static function register(): void
    {
        Event::on(
            Asset::class,
            Asset::EVENT_BEFORE_SAVE,
            static function (ModelEvent $event): void {
                /** @var Asset $asset */
                $asset = $event->sender;

                if ($asset->kind !== Asset::KIND_IMAGE) {
                    return;
                }

                if (!self::hasAverageColorField($asset)) {
                    return;
                }

                // Skip if not a new asset and file hasn't changed
                if (!empty($asset->getFieldValue('averageColor'))) {
                    return;
                }

                $color = self::calculateAverageColor($asset);

                if ($color !== null) {
                    $asset->setFieldValue('averageColor', $color);
                }
            }
        );
    }

    private static function hasAverageColorField(Asset $asset): bool
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

    private static function calculateAverageColor(Asset $asset): ?string
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

            $averageColor = self::calculateWithGd($imagePath);

            if ($averageColor !== null) {
                return $averageColor;
            }

            return self::calculateWithImagick($imagePath);
        } catch (Throwable) {
            return null;
        } finally {
            if ($temporaryPath !== null && is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }
        }
    }

    private static function calculateWithGd(string $imagePath): ?string
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

    private static function calculateWithImagick(string $imagePath): ?string
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
