<?php

namespace rondodevs\toolkit\streamingvideo;

use Craft;
use craft\base\AssetPreviewHandler;
use craft\elements\Asset;
use craft\web\View;
use rondodevs\toolkit\assets\streamingvideo\StreamingVideoEditorAsset;

/**
 * The CP preview of an HLS-ready video: plays the stream the frontend gets,
 * instead of the original file.
 */
class HlsPreview extends AssetPreviewHandler
{
    public function __construct(Asset $asset, private readonly string $playlistUrl, private readonly ?string $posterUrl)
    {
        parent::__construct($asset);
    }

    public function getPreviewHtml(array $variables = []): string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(StreamingVideoEditorAsset::class);

        return $view->renderTemplate('toolkit/streaming-video/_preview', array_merge([
            'asset' => $this->asset,
            'playlistUrl' => $this->playlistUrl,
            'posterUrl' => $this->posterUrl,
            'url' => $this->asset->getUrl(),
        ], $variables), View::TEMPLATE_MODE_CP);
    }
}
