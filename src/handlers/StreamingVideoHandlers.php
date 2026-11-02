<?php

namespace rondodevs\toolkit\handlers;

use Craft;
use craft\base\Element;
use craft\elements\Asset;
use craft\events\AssetPreviewEvent;
use craft\base\Model;
use craft\events\CreateFieldLayoutFormEvent;
use craft\events\DefineAssetThumbUrlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineRulesEvent;
use craft\models\FieldLayout;
use craft\events\DefineGqlTypeFieldsEvent;
use craft\events\DefineMetadataEvent;
use craft\events\ModelEvent;
use craft\gql\interfaces\elements\Asset as AssetInterface;
use craft\gql\TypeManager;
use craft\services\Assets;
use GraphQL\Type\Definition\Type;
use rondodevs\toolkit\streamingvideo\ConversionStatus;
use rondodevs\toolkit\streamingvideo\HlsPreview;
use rondodevs\toolkit\streamingvideo\StreamingVideoBehavior;
use rondodevs\toolkit\streamingvideo\StreamingVideoFormElement;
use rondodevs\toolkit\Toolkit;
use yii\base\Event;

class StreamingVideoHandlers
{
    public static function register(): void
    {
        $service = Toolkit::getInstance()->streamingVideo;

        // Always in the schema, so the same frontend query works where
        // streaming is off: the field is null there
        Event::on(
            TypeManager::class,
            TypeManager::EVENT_DEFINE_GQL_TYPE_FIELDS,
            static function (DefineGqlTypeFieldsEvent $event) use ($service): void {
                if ($event->typeName !== 'AssetInterface') {
                    return;
                }

                $event->fields['hlsPlaylistUrl'] = [
                    'name' => 'hlsPlaylistUrl',
                    'type' => Type::string(),
                    'description' => 'HLS master playlist URL, null until the video is encoded: play `url` meanwhile.',
                    'resolve' => static fn($source) => $source instanceof Asset ? $service->getPlaylistUrl($source) : null,
                ];

                $event->fields['mp4ProgressiveUrl'] = [
                    'name' => 'mp4ProgressiveUrl',
                    'type' => Type::string(),
                    'description' => 'Progressive MP4 (H.264/AAC, up to 1080p, fast start) of every video, HLS on or off: null until encoded. For players without HLS, downloads, social embeds.',
                    'resolve' => static fn($source) => $source instanceof Asset ? $service->getMp4ProgressiveUrl($source) : null,
                ];

                $event->fields['videoPoster'] = [
                    'name' => 'videoPoster',
                    'type' => AssetInterface::getType(),
                    'description' => 'Image asset shown before the video plays: a frame of the video (up to 1920px wide), or an image uploaded or chosen instead. Null until the first frame is extracted.',
                    'resolve' => static fn($source) => $source instanceof Asset && $service->isEnabled() ? $service->getPoster($source) : null,
                ];
            }
        );

        // The poster as the video's thumbnail in the CP, instead of the file icon
        Event::on(
            Assets::class,
            Assets::EVENT_DEFINE_THUMB_URL,
            static function (DefineAssetThumbUrlEvent $event) use ($service): void {
                if ($service->isVideo($event->asset) && $poster = $service->getPoster($event->asset)) {
                    $event->url = Craft::$app->getAssets()->getThumbUrl($poster, $event->width, $event->height);
                }
            }
        );

        // HLS files go with their asset, even if streaming was switched off since
        Event::on(
            Asset::class,
            Element::EVENT_AFTER_DELETE,
            static fn(Event $event) => $service->deleteHls($event->sender)
        );

        if (!$service->isEnabled()) {
            return;
        }

        // The Streaming block on every video's edit form, in every volume,
        // without a field: added when the form is built
        Event::on(
            FieldLayout::class,
            FieldLayout::EVENT_CREATE_FORM,
            static function (CreateFieldLayoutFormEvent $event): void {
                $asset = $event->element;

                if (!$asset instanceof Asset || $asset->kind !== Asset::KIND_VIDEO || !$event->tabs) {
                    return;
                }

                // A copy: the layout's own tabs are cached and shared
                $tab = clone $event->tabs[0];
                $block = new StreamingVideoFormElement();
                $block->setLayout($tab->getLayout());
                $tab->setElements([...$tab->getElements(), $block]);
                $event->tabs[0] = $tab;
            }
        );

        // Its values reach the asset like a native attribute
        Event::on(
            Asset::class,
            Model::EVENT_DEFINE_BEHAVIORS,
            static function (DefineBehaviorsEvent $event): void {
                $event->behaviors['toolkitStreaming'] = StreamingVideoBehavior::class;
            }
        );

        Event::on(
            Asset::class,
            Model::EVENT_DEFINE_RULES,
            static function (DefineRulesEvent $event): void {
                $event->rules[] = [[StreamingVideoFormElement::ATTRIBUTE], 'safe'];
            }
        );

        Event::on(
            Asset::class,
            Element::EVENT_AFTER_SAVE,
            static fn(ModelEvent $event) => $service->handleSave($event->sender)
        );

        // Conversion status in the asset sidebar
        Event::on(
            Asset::class,
            Element::EVENT_DEFINE_METADATA,
            static function (DefineMetadataEvent $event) use ($service): void {
                /** @var Asset $asset */
                $asset = $event->sender;

                if ($service->shouldEncode($asset)) {
                    $event->metadata['Streaming'] = static fn() => ($service->getStatus($asset) ?? ConversionStatus::Pending)->badgeHtml();
                }
            }
        );

        // The CP preview plays the HLS stream once it's ready, like the frontend
        Event::on(
            Assets::class,
            Assets::EVENT_REGISTER_PREVIEW_HANDLER,
            static function (AssetPreviewEvent $event) use ($service): void {
                $playlistUrl = $service->getPlaylistUrl($event->asset);

                if ($playlistUrl !== null) {
                    $event->previewHandler = new HlsPreview($event->asset, $playlistUrl, $service->getPoster($event->asset)?->getUrl());
                }
            }
        );
    }
}
