<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\web\View;
use rondodevs\toolkit\assets\cloudflare\CloudflareAsset;
use rondodevs\toolkit\Toolkit;
use rondodevs\toolkit\ToolkitSections;

class CloudflareUtility extends Utility
{
    public static function displayName(): string
    {
        return 'Cloudflare';
    }

    public static function id(): string
    {
        return 'toolkit-cloudflare';
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
            $view->registerAssetBundle(CloudflareAsset::class);

            return $view->renderTemplate('toolkit/utilities/cloudflare', [
                'formActionUrl' => UrlHelper::actionUrl('toolkit/cloudflare/save'),
                'redirectPath' => self::redirectPath(),
                'cloudflareEnabled' => (bool)$resolved['cloudflareEnabled'],
                'cloudflareZoneIdOverride' => (string)($overrides['cloudflareZoneId'] ?? ''),
                'cloudflareZoneIdDefault' => (string)$defaults['cloudflareZoneId'],
                'cloudflareApiTokenOverride' => (string)($overrides['cloudflareApiToken'] ?? ''),
                'cloudflareApiTokenDefault' => (string)$defaults['cloudflareApiToken'],
                'cloudflareCreateTokenUrl' => self::cloudflareCreateTokenUrl(),
                'kvCacheUrl' => ToolkitSections::url('kv-cache'),
                'isDevMode' => App::devMode(),
                'iconSvg' => ToolkitSections::iconSvg('cloudflare'),
            ]);
        } finally {
            $view->setTemplateMode($oldTemplateMode);
        }
    }

    /**
     * Cloudflare's token template URL: opens the token form with only the "Cache Purge"
     * permission and a name filled in. The zone is narrowed by hand on that form.
     * https://developers.cloudflare.com/fundamentals/api/how-to/account-owned-token-template/
     */
    private static function cloudflareCreateTokenUrl(): string
    {
        return 'https://dash.cloudflare.com/profile/api-tokens?' . http_build_query([
            'permissionGroupKeys' => json_encode([['key' => 'cache', 'type' => 'purge']]),
            'accountId' => '*',
            'zoneId' => 'all',
            'name' => 'Craft cache purge – ' . Craft::$app->getSites()->getPrimarySite()->getName(),
        ], '', '&', PHP_QUERY_RFC3986);
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
