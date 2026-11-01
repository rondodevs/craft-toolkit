<?php

namespace rondodevs\toolkit\cp\screens;

use Craft;
use rondodevs\toolkit\services\StreamingVideoService;
use rondodevs\toolkit\streamingvideo\ConversionStatus;
use rondodevs\toolkit\streamingvideo\EncodingProfile;
use rondodevs\toolkit\streamingvideo\Poster;
use rondodevs\toolkit\Toolkit;

final class StreamingVideoScreen implements Screen
{
    public static function props(): array
    {
        $service = Toolkit::getInstance()->streamingVideo;
        $enabled = $service->isEnabled();
        $counts = $enabled ? $service->getStatusCounts() : [];

        return [
            'enabled' => $enabled,
            'envVar' => StreamingVideoService::ENV_VAR,
            'statuses' => array_values(array_map(static fn(ConversionStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
                'count' => $counts[$status->value] ?? 0,
            ], array_filter(ConversionStatus::cases(), static fn($status) => $status !== ConversionStatus::Off))),
            'failures' => $enabled ? array_map(static fn(array $failure) => [
                'assetId' => $failure['assetId'],
                'title' => $failure['asset']?->title,
                'cpEditUrl' => $failure['asset']?->getCpEditUrl(),
                'error' => $failure['error'],
                'dateUpdated' => Craft::$app->getFormatter()->asDatetime($failure['dateUpdated'], 'short'),
            ], $service->getRecentFailures()) : [],
            'settings' => $service->getSettings(),
            'qualityOptions' => array_map(static function(int $height) {
                [$video, $audio] = EncodingProfile::QUALITIES[$height];

                return ['label' => "{$height}p ({$video} kbps video, {$audio} kbps audio)", 'value' => $height];
            }, StreamingVideoService::hdQualities()),
            'segmentDuration' => EncodingProfile::SEGMENT_DURATION,
            'defaultPosterTime' => Poster::DEFAULT_TIME,
            'aboveHdBitrates' => [
                '1440' => EncodingProfile::QUALITIES[1440][0],
                '2160' => EncodingProfile::QUALITIES[2160][0],
            ],
        ];
    }
}
