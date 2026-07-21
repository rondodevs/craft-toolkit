<?php

namespace rondodevs\toolkit\assets\orgschemafield;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class OrgSchemaFieldAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = '@rondodevs/toolkit/resources/org-schema-field';
        $this->depends = [
            CpAsset::class,
        ];
        $this->css = [
            'css/org-schema-field.css',
        ];
        $this->js = [
            'js/org-schema-field.js',
        ];

        parent::init();
    }
}
