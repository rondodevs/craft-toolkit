<?php

namespace rondodevs\toolkit\assets\streamingvideo;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Asset edit screens: the poster frame picker and the HLS preview.
 */
class StreamingVideoEditorAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@rondodevs/toolkit/resources/streaming-video';
        $this->depends = [
            CpAsset::class,
        ];
        $this->css = [
            'css/editor.css',
        ];
        $this->js = [
            'js/editor.js',
        ];

        parent::init();
    }
}
