<?php

namespace rondodevs\toolkit\assets\averagecolor;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class AverageColorAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@rondodevs/toolkit/resources/average-color';
        $this->depends = [
            CpAsset::class,
        ];
        $this->css = [
            'css/average-color.css',
        ];
        $this->js = [
            'js/average-color.js',
        ];

        parent::init();
    }
}
