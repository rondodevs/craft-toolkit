<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\web\View;
use rondodevs\toolkit\Toolkit;

class RedirectUtility extends Utility
{
    public static function displayName(): string
    {
        return 'Redirect';
    }

    public static function id(): string
    {
        return 'toolkit-redirect';
    }

    public static function iconPath()
    {
        return null;
    }

    public static function contentHtml(): string
    {
        $service = Toolkit::getInstance()->redirect;
        $defaults = $service->getDefaultSettings();
        $overrides = $service->getOverrides();
        $resolved = $service->getResolvedSettings();

        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);

            return $view->renderTemplate('toolkit/utilities/redirect', [
                'formActionUrl' => UrlHelper::actionUrl('toolkit/redirect/save'),
                'redirectPath' => self::redirectPath(),
                'enabled' => (bool)$resolved['enabled'],
                'frontendUrlOverride' => (string)($overrides['frontendUrl'] ?? ''),
                'frontendUrlDefault' => (string)$defaults['frontendUrl'],
                'resolvedFrontendUrl' => (string)$resolved['frontendUrl'],
                'isDevMode' => App::devMode(),
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
