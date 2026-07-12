<?php

namespace rondodevs\toolkit\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * Hr Preview asset bundle
 */
class ToolkitAsset extends AssetBundle
{
    // Where our JS "lives"
    public $sourcePath = '@rondodevs/toolkit';
    
    // Ensure CP’s core scripts (including garnish.js) are available
    public $depends = [
        CpAsset::class,
    ];
    
    // Our custom script
    public $js = [
        'script.js',
    ];
}
