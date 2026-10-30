<?php

namespace rondodevs\toolkit\streamingvideo;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\fieldlayoutelements\BaseNativeField;
use rondodevs\toolkit\assets\streamingvideo\StreamingVideoEditorAsset;
use rondodevs\toolkit\Toolkit;

/**
 * The Streaming block of a video's edit form: HLS on or off, status,
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
        $view = Craft::$app->getView();
        $view->registerAssetBundle(StreamingVideoEditorAsset::class);

        return $view->renderTemplate('toolkit/streaming-video/_field', [
            'name' => self::ATTRIBUTE,
            'value' => ['enabled' => $element->id && $service->shouldEncode($element), 'posterTime' => $service->getPickedTime($element)],
            'asset' => $element,
            'envEnabled' => $service->isEnabled(),
            'envVar' => $service::ENV_VAR,
            'status' => $element->id ? $service->getStatus($element) : null,
            'pending' => ConversionStatus::Pending,
            'url' => $element->getUrl(),
            'playlistUrl' => $element->id ? $service->getPlaylistUrl($element) : null,
            'poster' => $poster,
            'posterThumbUrl' => $poster ? Craft::$app->getAssets()->getThumbUrl($poster, 640, 360) : null,
            'customPoster' => $service->isCustomPoster($element),
            'posterPending' => $element->id && $service->needsPoster($element),
            'posterTime' => $element->id ? $service->getPosterTime($element) : Poster::DEFAULT_TIME,
            'candidates' => $element->id ? $service->getCandidates($element) : null,
            'extensions' => Poster::EXTENSIONS,
            'volumeUid' => $element->getVolume()->uid,
        ], \craft\web\View::TEMPLATE_MODE_CP);
    }

    // Read from the toolkit's table, not from an attribute of the asset
    protected function value(?ElementInterface $element = null): mixed
    {
        return null;
    }
}
