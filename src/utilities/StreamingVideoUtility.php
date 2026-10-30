<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\UrlHelper;
use craft\web\View;
use rondodevs\toolkit\assets\streamingvideo\StreamingVideoAsset;
use rondodevs\toolkit\services\StreamingVideoService;
use rondodevs\toolkit\streamingvideo\ConversionStatus;
use rondodevs\toolkit\streamingvideo\EncodingProfile;
use rondodevs\toolkit\streamingvideo\Poster;
use rondodevs\toolkit\Toolkit;
use rondodevs\toolkit\ToolkitSections;

class StreamingVideoUtility extends Utility
{
    public static function displayName(): string
    {
        return 'Streaming Video';
    }

    public static function id(): string
    {
        return 'toolkit-streaming-video';
    }

    public static function iconPath()
    {
        return null;
    }

    public static function contentHtml(): string
    {
        $service = Toolkit::getInstance()->streamingVideo;
        $enabled = $service->isEnabled();

        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);
            $view->registerAssetBundle(StreamingVideoAsset::class);

            return $view->renderTemplate('toolkit/utilities/streaming-video', [
                'iconSvg' => ToolkitSections::iconSvg('streaming-video'),
                'redirectPath' => self::redirectPath(),
                'enabled' => $enabled,
                'envVar' => $service::ENV_VAR,
                'statuses' => array_filter(ConversionStatus::cases(), static fn($status) => $status !== ConversionStatus::Off),
                'statusCounts' => $enabled ? $service->getStatusCounts() : [],
                'volumeStatuses' => $service->getVolumeStatuses(),
                'allowAdminChanges' => Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
                'failures' => $enabled ? $service->getRecentFailures() : [],
                'settings' => $service->getSettings(),
                'qualityOptions' => self::qualityOptions(),
                'segmentDuration' => EncodingProfile::SEGMENT_DURATION,
                'defaultPosterTime' => Poster::DEFAULT_TIME,
                'aboveHdBitrates' => [1440 => EncodingProfile::QUALITIES[1440], 2160 => EncodingProfile::QUALITIES[2160]],
            ]);
        } finally {
            $view->setTemplateMode($oldTemplateMode);
        }
    }

    private static function qualityOptions(): array
    {
        $options = [];

        foreach (StreamingVideoService::hdQualities() as $height) {
            [$video, $audio] = EncodingProfile::QUALITIES[$height];
            $options[] = ['label' => "{$height}p ({$video} kbps video, {$audio} kbps audio)", 'value' => $height];
        }

        return $options;
    }

    private static function redirectPath(): string
    {
        $path = trim((string)Craft::$app->getRequest()->getPathInfo(), '/');

        if ($path !== '') {
            return $path;
        }

        return 'utilities/' . self::id();
    }
}
