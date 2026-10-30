<?php

namespace rondodevs\toolkit\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\Asset;
use GraphQL\Type\Definition\Type;
use rondodevs\toolkit\assets\streamingvideo\StreamingVideoEditorAsset;
use rondodevs\toolkit\streamingvideo\ConversionStatus;
use rondodevs\toolkit\streamingvideo\Poster;
use rondodevs\toolkit\Toolkit;
use yii\db\Schema;

/**
 * Goes in a volume's field layout: on video files it switches HLS encoding
 * on (off by default), shows the status and sets the poster, a frame of the
 * video or an image uploaded or chosen instead.
 *
 * Value: ['enabled' => bool, 'posterTime' => ?float], plus the poster
 * changes posted with the form ('posterImageId', 'resetPoster').
 */
class StreamingVideoField extends Field
{
    public static function displayName(): string
    {
        return 'Streaming Video';
    }

    public static function icon(): string
    {
        return 'film';
    }

    public static function phpType(): string
    {
        return 'array';
    }

    public static function dbType(): array|string|null
    {
        return Schema::TYPE_TEXT;
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        $value = is_array($value) ? $value : [];
        $time = $value['posterTime'] ?? null;
        $imageId = $value['posterImageId'] ?? null;

        return [
            'enabled' => (bool)($value['enabled'] ?? false),
            'posterTime' => is_numeric($time) ? max(0, (float)$time) : null,
            // Poster changes posted with the form, applied after the save and never stored
            'posterImageId' => is_numeric($imageId) ? (int)$imageId : null,
            'resetPoster' => (bool)($value['resetPoster'] ?? false),
        ];
    }

    public function serializeValue(mixed $value, ?ElementInterface $element): mixed
    {
        $value = $this->normalizeValue($value, $element);

        return json_encode(['enabled' => $value['enabled'], 'posterTime' => $value['posterTime']]);
    }

    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        return !($value['enabled'] ?? false);
    }

    public function getContentGqlType(): Type|array
    {
        return [
            'name' => $this->handle,
            'type' => Type::boolean(),
            'description' => 'Whether the video is converted to HLS: query hlsPlaylistUrl for the stream.',
            'resolve' => fn($source) => (bool)($source->getFieldValue($this->handle)['enabled'] ?? false),
        ];
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        if (!$element instanceof Asset || $element->kind !== Asset::KIND_VIDEO) {
            return '<p class="light">Only used on video files.</p>';
        }

        $service = Toolkit::getInstance()->streamingVideo;
        $poster = $service->getPoster($element);
        $view = Craft::$app->getView();
        $view->registerAssetBundle(StreamingVideoEditorAsset::class);

        return $view->renderTemplate('toolkit/streaming-video/_field', [
            'name' => $this->handle,
            'value' => $value,
            'asset' => $element,
            'envEnabled' => $service->isEnabled(),
            'envVar' => $service::ENV_VAR,
            'status' => $element->id ? ($service->getStatus($element) ?? null) : null,
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
        ]);
    }
}
