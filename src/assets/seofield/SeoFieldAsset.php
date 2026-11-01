<?php

namespace rondodevs\toolkit\assets\seofield;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class SeoFieldAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@rondodevs/toolkit/resources/seo-field';
        $this->depends = [
            CpAsset::class,
        ];
        $this->css = [
            'css/seo-field.css',
        ];
        $this->js = [
            'js/seo-field.js',
        ];

        parent::init();
    }
}
