<?php

namespace rondodevs\toolkit\assets\staticlabels;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class StaticLabelsAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@rondodevs/toolkit/resources/static-labels';
        $this->depends = [
            CpAsset::class,
        ];
        $this->css = [
            'css/static-labels.css',
        ];
        $this->js = [
            'js/static-labels.js',
        ];

        parent::init();
    }
}
