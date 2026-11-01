<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\UrlHelper;
use craft\web\View;
use rondodevs\toolkit\seo\EtherLegacy;
use rondodevs\toolkit\Toolkit;
use rondodevs\toolkit\ToolkitSections;

class SeoUtility extends Utility
{
    public static function displayName(): string
    {
        return 'SEO';
    }

    public static function id(): string
    {
        return 'toolkit-seo';
    }

    public static function iconPath()
    {
        return null;
    }

    public static function contentHtml(): string
    {
        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();
        $seo = Toolkit::getInstance()->seo;

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);

            return $view->renderTemplate('toolkit/utilities/seo', [
                'iconSvg' => ToolkitSections::iconSvg('seo'),
                'etherInstalled' => $seo->isEtherInstalled(),
                'etherInComposer' => $seo->isEtherInComposer(),
                'etherFields' => $seo->getEtherFields(),
                'etherRedirectCount' => $seo->getEtherRedirectCount(),
                'toolkitFields' => $seo->getToolkitFields(),
                'usages' => $seo->getUsages(),
                'canMigrate' => $seo->canMigrate(),
                'compatEnabled' => EtherLegacy::isGraphqlCompatEnabled(),
                'deprecationsUrl' => UrlHelper::cpUrl('utilities/deprecation-errors'),
                'fieldsUrl' => UrlHelper::cpUrl('settings/fields'),
            ]);
        } finally {
            $view->setTemplateMode($oldTemplateMode);
        }
    }
}
