<?php

namespace rondodevs\toolkit\assets\kvcache;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class KvCacheAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@rondodevs/toolkit/resources/kv-cache';
        $this->depends = [
            CpAsset::class,
        ];
        $this->css = [
            'css/kv-cache.css',
        ];
        $this->js = [
            'js/kv-cache.js',
        ];

        parent::init();
    }
}
