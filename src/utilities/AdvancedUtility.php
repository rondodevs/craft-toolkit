<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\web\View;
use rondodevs\toolkit\assets\purge\PurgeAsset;
use rondodevs\toolkit\ToolkitSections;

class AdvancedUtility extends Utility
{
    public static function displayName(): string
    {
        return 'Advanced';
    }

    public static function id(): string
    {
        return 'toolkit-advanced';
    }

    public static function iconPath()
    {
        return null;
    }

    public static function contentHtml(): string
    {
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);
            $view->registerAssetBundle(PurgeAsset::class);

            return $view->renderTemplate('toolkit/utilities/advanced', [
                'kvCacheUrl' => ToolkitSections::url('kv-cache'),
                'cloudflareUrl' => ToolkitSections::url('cloudflare'),
                'iconSvg' => ToolkitSections::iconSvg('advanced'),
            ]);
        } finally {
            $view->setTemplateMode($oldTemplateMode);
        }
    }
}
