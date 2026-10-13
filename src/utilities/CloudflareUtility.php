<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\web\View;
use rondodevs\toolkit\assets\cloudflare\CloudflareAsset;
use rondodevs\toolkit\services\CloudflareCacheRuleService;
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
                'cloudflareCreateTokenUrl' => Toolkit::getInstance()->cloudflareCacheRule->createTokenUrl(),
                'kvCacheUrl' => ToolkitSections::url('kv-cache'),
                'advancedUrl' => ToolkitSections::url('advanced'),
                'cacheRuleName' => CloudflareCacheRuleService::RULE_DESCRIPTION,
                'cacheRuleExpression' => self::cacheRuleExpression((string)$resolved['frontendUrl']),
                'isDevMode' => App::devMode(),
                'iconSvg' => ToolkitSections::iconSvg('cloudflare'),
            ]);
        } finally {
            $view->setTemplateMode($oldTemplateMode);
        }
    }

    /**
     * The Cache Rule expression for the Frontend URL's host, to create the rule by hand. The host may
     * still redirect (apex → www): the check and the Create button use the host after redirects.
     */
    private static function cacheRuleExpression(string $frontendUrl): ?string
    {
        $host = parse_url($frontendUrl, PHP_URL_HOST);

        return $host ? Toolkit::getInstance()->cloudflareCacheRule->buildExpression($host) : null;
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
