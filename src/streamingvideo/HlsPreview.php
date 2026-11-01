<?php

namespace rondodevs\toolkit\streamingvideo;

use craft\base\AssetPreviewHandler;
use craft\elements\Asset;
use rondodevs\toolkit\web\Island;

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
        return Island::render('hls-preview', [
            'playlistUrl' => $this->playlistUrl,
            'url' => $this->asset->getUrl(),
            'posterUrl' => $this->posterUrl,
            'transcript' => $this->asset->alt,
        ], ['class' => 'toolkit-hls-preview', 'style' => 'height: 100%;']);
    }
}
