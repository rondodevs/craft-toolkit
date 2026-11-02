<?php

namespace rondodevs\toolkit\streamingvideo;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\fieldlayoutelements\BaseNativeField;
use rondodevs\toolkit\Toolkit;
use rondodevs\toolkit\web\Island;

/**
 * The Streaming block of a video's edit form: HLS on or off, status of the
 * encoding (MP4 always, HLS when on),
 * Re-encode and the poster. Added to the form of every video by
 * StreamingVideoHandlers, in every volume: no field, nothing in the layouts.
 * Its values reach the asset as `toolkitStreaming` (StreamingVideoBehavior).
 */
class StreamingVideoFormElement extends BaseNativeField
{
    public const ATTRIBUTE = 'toolkitStreaming';

    public string $attribute = self::ATTRIBUTE;

    protected function defaultLabel(?ElementInterface $element = null, bool $static = false): ?string
    {
        return 'Streaming';
    }

    protected function inputHtml(?ElementInterface $element = null, bool $static = false): ?string
    {
        if (!$element instanceof Asset || $element->kind !== Asset::KIND_VIDEO) {
            return null;
        }

        $service = Toolkit::getInstance()->streamingVideo;
        $poster = $service->getPoster($element);
        $saved = (bool)$element->id;

        return Island::render('streaming-video', [
            'name' => Craft::$app->getView()->namespaceInputName(self::ATTRIBUTE),
            'assetId' => $element->id,
            'enabled' => $saved && $service->isHlsEnabled($element),
            'envEnabled' => $service->isEnabled(),
            'envVar' => $service::ENV_VAR,
            'status' => $saved && $service->shouldEncode($element) ? self::status($service->getStatus($element) ?? ConversionStatus::Pending) : null,
            'url' => $element->getUrl(),
            'poster' => [
                'thumbUrl' => $poster ? Craft::$app->getAssets()->getThumbUrl($poster, 640, 360) : null,
                'custom' => $service->isCustomPoster($element),
                // The frame the poster shows, null for a custom image or none yet
                'time' => $poster && !$service->isCustomPoster($element) ? $service->getPosterTime($element) : null,
                'pending' => $saved && $service->needsPoster($element),
            ],
            'pickedTime' => $service->getPickedTime($element),
            'posterTime' => $saved ? $service->getPosterTime($element) : Poster::DEFAULT_TIME,
            'candidates' => $saved ? $service->getCandidates($element) : null,
            'extensions' => Poster::EXTENSIONS,
            'volumeUid' => $element->getVolume()->uid,
        ]);
    }

    private static function status(ConversionStatus $status): array
    {
        return ['label' => $status->label(), 'color' => $status->color()];
    }

    // Read from the toolkit's table, not from an attribute of the asset
    protected function value(?ElementInterface $element = null): mixed
    {
        return null;
    }
}
