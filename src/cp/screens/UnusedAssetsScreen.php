<?php

namespace rondodevs\toolkit\cp\screens;

use Craft;
use craft\elements\Asset;
use rondodevs\toolkit\services\UnusedAssetsService;
use rondodevs\toolkit\Toolkit;

final class UnusedAssetsScreen implements Screen
{
    public static function props(): array
    {
        $volumes = Craft::$app->getVolumes();
        $volume = $volumes->getVolumeByHandle('media') ?? $volumes->getAllVolumes()[0] ?? null;

        return [
            'volumes' => array_map(static fn($v) => ['label' => $v->name, 'value' => $v->id], $volumes->getAllVolumes()),
            'volumeId' => $volume?->id,
            'usedWhen' => UnusedAssetsService::usedWhen(),
            'limit' => UnusedAssetsService::LIMIT,
            'result' => $volume ? self::result($volume->id) : null,
        ];
    }

    /**
     * The unused assets of a volume, as the list shows them.
     */
    public static function result(int $volumeId): array
    {
        $service = Toolkit::getInstance()->unusedAssets;
        $formatter = Craft::$app->getFormatter();

        return [
            'volumeName' => Craft::$app->getVolumes()->getVolumeById($volumeId)?->name,
            'total' => $service->count($volumeId),
            'assets' => array_map(static fn(Asset $asset) => [
                'id' => $asset->id,
                'title' => (string)$asset->title,
                'filename' => $asset->filename,
                'cpEditUrl' => $asset->getCpEditUrl(),
                'thumbUrl' => Craft::$app->getAssets()->getThumbUrl($asset, 60, 60),
                'size' => (int)$asset->size,
                'dateCreated' => $formatter->asDatetime($asset->dateCreated, 'short'),
            ], $service->find($volumeId)),
        ];
    }
}
