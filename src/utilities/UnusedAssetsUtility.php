<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\web\View;
use rondodevs\toolkit\services\UnusedAssetsService;
use rondodevs\toolkit\Toolkit;
use rondodevs\toolkit\ToolkitSections;

class UnusedAssetsUtility extends Utility
{
    public static function displayName(): string
    {
        return 'Unused Assets';
    }

    public static function id(): string
    {
        return 'toolkit-unused-assets';
    }

    public static function iconPath()
    {
        return null;
    }

    public static function contentHtml(): string
    {
        $service = Toolkit::getInstance()->unusedAssets;
        $volumes = Craft::$app->getVolumes()->getAllVolumes();
        $volume = self::selectedVolume($volumes);

        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);

            return $view->renderTemplate('toolkit/utilities/unused-assets', [
                'iconSvg' => ToolkitSections::iconSvg('unused-assets'),
                'redirectPath' => self::redirectPath(),
                'volumeOptions' => array_map(static fn($v) => ['label' => $v->name, 'value' => $v->id], $volumes),
                'volume' => $volume,
                'assets' => $volume ? $service->find($volume->id) : [],
                'total' => $volume ? $service->count($volume->id) : 0,
                'limit' => UnusedAssetsService::LIMIT,
                'usedWhen' => UnusedAssetsService::usedWhen(),
            ]);
        } finally {
            $view->setTemplateMode($oldTemplateMode);
        }
    }

    /**
     * The volume in the query string, else `media`, else the first one.
     */
    private static function selectedVolume(array $volumes): mixed
    {
        $volumeId = (int)Craft::$app->getRequest()->getQueryParam('volumeId');

        foreach ($volumes as $volume) {
            if ($volume->id === $volumeId) {
                return $volume;
            }
        }

        return Craft::$app->getVolumes()->getVolumeByHandle('media') ?? $volumes[0] ?? null;
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
