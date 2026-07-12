<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\UrlHelper;
use craft\web\View;
use rondodevs\toolkit\assets\staticlabels\StaticLabelsAsset;
use rondodevs\toolkit\Toolkit;

class StaticLabelsUtility extends Utility
{
    public static function displayName(): string
    {
        return 'Static Labels';
    }

    public static function id(): string
    {
        return 'toolkit-static-labels';
    }

    public static function iconPath()
    {
        return null;
    }

    public static function contentHtml(): string
    {
        $service = Toolkit::getInstance()->staticLabels;
        $resolved = $service->getResolvedStaticLabels();
        $sites = self::normalizeSites(is_array($resolved['sites'] ?? null) ? $resolved['sites'] : []);
        $firstSite = $sites[0] ?? null;

        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);
            $view->registerAssetBundle(StaticLabelsAsset::class);

            return $view->renderTemplate('toolkit/utilities/static-labels', [
                'sites' => $sites,
                'firstSite' => $firstSite,
                'formActionUrl' => UrlHelper::actionUrl('toolkit/static-labels/save'),
                'redirectPath' => self::redirectPath(),
            ]);
        } finally {
            $view->setTemplateMode($oldTemplateMode);
        }
    }

    private static function normalizeSites(array $sites): array
    {
        $normalized = [];

        foreach ($sites as $siteConfig) {
            if (!is_array($siteConfig)) {
                continue;
            }

            $labels = [];
            $sourceLabels = is_array($siteConfig['labels'] ?? null) ? $siteConfig['labels'] : [];

            foreach ($sourceLabels as $labelRow) {
                if (!is_array($labelRow)) {
                    continue;
                }

                $singleValue = (string)($labelRow['singleValue'] ?? $labelRow['value'] ?? '');
                $labels[] = [
                    'key' => (string)($labelRow['key'] ?? ''),
                    'mode' => (string)($labelRow['mode'] ?? 'single'),
                    'singleValue' => $singleValue,
                    'zeroValue' => (string)($labelRow['zeroValue'] ?? ''),
                    'manyValue' => (string)($labelRow['manyValue'] ?? ''),
                ];
            }

            if ($labels === []) {
                $labels[] = [
                    'key' => '',
                    'mode' => 'single',
                    'singleValue' => '',
                    'zeroValue' => '',
                    'manyValue' => '',
                ];
            }

            $normalized[] = [
                'siteHandle' => (string)($siteConfig['siteHandle'] ?? ''),
                'siteName' => (string)($siteConfig['siteName'] ?? ''),
                'latestStaticLabelsUpdate' => (string)($siteConfig['latestStaticLabelsUpdate'] ?? 'never'),
                'labels' => $labels,
            ];
        }

        return $normalized;
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