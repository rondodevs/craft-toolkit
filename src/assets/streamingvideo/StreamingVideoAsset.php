<?php

namespace rondodevs\toolkit\assets\streamingvideo;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class StreamingVideoAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@rondodevs/toolkit/resources/streaming-video';
        $this->depends = [
            CpAsset::class,
        ];
        $this->css = [
            'css/streaming-video.css',
        ];
        $this->js = [
            'js/streaming-video.js',
        ];

        parent::init();
    }
}
