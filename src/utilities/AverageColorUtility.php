<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\UrlHelper;
use craft\web\View;
use rondodevs\toolkit\assets\averagecolor\AverageColorAsset;
use rondodevs\toolkit\Toolkit;

class AverageColorUtility extends Utility
{
    public static function displayName(): string
    {
        return 'Average Color Utility';
    }

    public static function id(): string
    {
        return 'toolkit-average-color';
    }

    public static function iconPath()
    {
        return null;
    }

    public static function contentHtml(): string
    {
        $service = Toolkit::getInstance()->averageColor;
        $resolved = $service->getResolvedSettings();

        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);
            $view->registerAssetBundle(AverageColorAsset::class);

            return $view->renderTemplate('toolkit/utilities/average-color', [
                'formActionUrl' => UrlHelper::actionUrl('toolkit/average-color/save'),
                'redirectPath' => self::redirectPath(),
                'enabled' => (bool)$resolved['enabled'],
                'volumeStatuses' => $service->getVolumeStatuses(),
            ]);
        } finally {
            $view->setTemplateMode($oldTemplateMode);
        }
    }

    private static function redirectPath(): string
    {
        $path = trim((string)Craft::$app->getRequest()->getPathInfo(), '/');

        if ($path !== '') {
            return $path;
        }

        return 'utilities/' . self::id();
    }
}
