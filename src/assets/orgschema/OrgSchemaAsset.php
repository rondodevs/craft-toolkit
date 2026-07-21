<?php

namespace rondodevs\toolkit\assets\orgschema;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class OrgSchemaAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@rondodevs/toolkit/resources/org-schema';
        $this->depends = [
            CpAsset::class,
        ];
        $this->css = [
            'css/org-schema.css',
        ];
        $this->js = [
            'js/org-schema.js',
        ];

        parent::init();
    }
}
