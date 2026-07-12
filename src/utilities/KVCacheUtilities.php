<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\UrlHelper;
use craft\web\View;
use rondodevs\toolkit\assets\kvcache\KvCacheAsset;
use rondodevs\toolkit\Toolkit;

class KVCacheUtilities extends Utility
{
    public static function displayName(): string
    {
        return 'KV Cache Utility';
    }

    public static function id(): string
    {
        return 'toolkit-kv-cache';
    }

    public static function iconPath()
    {
        return null;
    }

    public static function contentHtml(): string
    {
        $service = Toolkit::getInstance()->kvCache;
        $defaults = $service->getDefaultSettings();
        $overrides = $service->getOverrides();
        $resolved = $service->getResolvedSettings();

        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);
            $view->registerAssetBundle(KvCacheAsset::class);

            return $view->renderTemplate('toolkit/utilities/kv-cache', [
                'formActionUrl' => UrlHelper::actionUrl('toolkit/kv-cache/save'),
                'redirectPath' => self::redirectPath(),
                'enabled' => (bool)$resolved['enabled'],
                'frontendUrlOverride' => (string)($overrides['frontendUrl'] ?? ''),
                'frontendUrlDefault' => (string)$defaults['frontendUrl'],
                'authTokenOverride' => (string)($overrides['authToken'] ?? ''),
                'authTokenDefault' => (string)$defaults['authToken'],
                'authHeaderNameOverride' => (string)($overrides['authHeaderName'] ?? ''),
                'authHeaderNameDefault' => (string)$defaults['authHeaderName'],
                'requestTimeoutOverride' => $overrides['requestTimeout'] ?? null,
                'requestTimeoutDefault' => (int)$defaults['requestTimeout'],
                'connectTimeoutOverride' => $overrides['connectTimeout'] ?? null,
                'connectTimeoutDefault' => (int)$defaults['connectTimeout'],
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
