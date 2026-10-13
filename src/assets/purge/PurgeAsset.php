<?php

namespace rondodevs\toolkit\assets\purge;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class PurgeAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@rondodevs/toolkit/resources/purge';
        $this->depends = [
            CpAsset::class,
        ];
        $this->js = [
            'js/purge.js',
        ];

        parent::init();
    }
}
