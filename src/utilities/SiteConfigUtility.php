<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use rondodevs\toolkit\Toolkit;

class SiteConfigUtility extends Utility
{
    public static function displayName(): string
    {
        return 'Site Config';
    }

    public static function id(): string
    {
        return 'toolkit-site-config';
    }

    /**
     * Puoi restituire il percorso di un file SVG come icona per l'utility.
     * Ad esempio, puoi mettere un file `icon.svg` nella stessa cartella di questa classe
     * e restituirne il percorso così:
     */
    public static function iconPath()
    {
        // @todo: creare un'icona;
        // return __DIR__ . '/icon.svg';
        return null;
    }

    public static function contentHtml(): string
    {
        $service = Toolkit::getInstance()->siteConfig;
        $defaults = $service->getDefaultSiteConfig();
        $overrides = $service->getOverrides();
        $resolved = $service->getResolvedSiteConfig();
        $redirectPath = self::redirectPath();

        $html = '<div class="pane">';
        $html .= '<h2>Site Config</h2>';
        $html .= '<p class="light">Defaults come from env vars CRAFT_SITE_NAME and CRAFT_FRONTEND_URL. You can override both values here.</p>';

        $html .= '<form method="post" accept-charset="UTF-8" action="' . Html::encode(UrlHelper::actionUrl('toolkit/site-config/save')) . '">';
        $html .= Html::csrfInput();
        $html .= Html::actionInput('toolkit/site-config/save');
        $html .= Html::redirectInput($redirectPath);

        $html .= '<div class="field">';
        $html .= '<div class="heading"><label for="toolkit-site-name">Site name override</label></div>';
        $html .= '<div class="input ltr">';
        $html .= Html::textInput('siteName', (string)($overrides['siteName'] ?? ''), [
            'id' => 'toolkit-site-name',
            'class' => 'text fullwidth',
            'placeholder' => (string)$defaults['siteName'],
        ]);
        $html .= '</div>';
        $html .= '<p class="light">Default: ' . Html::encode((string)$defaults['siteName']) . '</p>';
        $html .= '</div>';

        $html .= '<div class="field">';
        $html .= '<div class="heading"><label for="toolkit-site-url">Site URL override</label></div>';
        $html .= '<div class="input ltr">';
        $html .= Html::textInput('siteUrl', (string)($overrides['siteUrl'] ?? ''), [
            'id' => 'toolkit-site-url',
            'class' => 'text fullwidth',
            'placeholder' => (string)$defaults['siteUrl'],
        ]);
        $html .= '</div>';
        $html .= '<p class="light">Default: ' . Html::encode((string)$defaults['siteUrl']) . '</p>';
        $html .= '</div>';

        $html .= '<div class="field">';
        $html .= '<p><strong>Resolved output</strong></p>';
        $html .= '<p class="light">siteName: ' . Html::encode((string)$resolved['siteName']) . '</p>';
        $html .= '<p class="light">siteUrl: ' . Html::encode((string)$resolved['siteUrl']) . '</p>';
        $html .= '<p class="light">latestSiteConfigUpdate: ' . Html::encode((string)($resolved['latestSiteConfigUpdate'] ?? 'null')) . '</p>';
        $html .= '</div>';

        $html .= '<div class="buttons">';
        $html .= '<button type="submit" class="btn submit">Save overrides</button>';
        $html .= '</div>';
        $html .= '</form>';
        $html .= '</div>';

        return $html;
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
