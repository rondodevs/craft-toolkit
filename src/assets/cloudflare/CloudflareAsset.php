<?php

namespace rondodevs\toolkit\assets\cloudflare;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class CloudflareAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@rondodevs/toolkit/resources/cloudflare';
        $this->depends = [
            CpAsset::class,
        ];
        $this->js = [
            'js/cloudflare.js',
        ];

        parent::init();
    }
}
