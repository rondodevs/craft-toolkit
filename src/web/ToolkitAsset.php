<?php

namespace rondodevs\toolkit\web;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * The CP app built by Vite from app/ (`pnpm build`): one module on every CP page, which mounts
 * the islands it finds and loads each one's chunk on demand.
 */
class ToolkitAsset extends AssetBundle
{
    public $sourcePath = '@rondodevs/toolkit/web/dist';

    // Craft's globals (Craft, Garnish) are there before the module runs
    public $depends = [
        CpAsset::class,
    ];

    public $js = [
        'toolkit.js',
    ];

    public $jsOptions = [
        'type' => 'module',
    ];

    public $css = [
        'toolkit.css',
    ];
}
