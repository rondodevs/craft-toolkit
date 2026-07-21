<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\elements\Asset;
use craft\helpers\Cp;
use craft\helpers\Json as JsonHelper;
use craft\helpers\UrlHelper;
use craft\web\View;
use rondodevs\toolkit\assets\orgschema\OrgSchemaAsset;
use rondodevs\toolkit\services\OrgSchemaService;
use rondodevs\toolkit\Toolkit;

class OrgSchemaUtility extends Utility
{
    public static function displayName(): string
    {
        return 'Org Schema';
    }

    public static function id(): string
    {
        return 'toolkit-org-schema';
    }

    public static function iconPath()
    {
        return null;
    }

    public static function contentHtml(): string
    {
        $service = Toolkit::getInstance()->orgSchema;
        $resolved = $service->getResolvedOrgSchema();
        $sites = is_array($resolved['sites'] ?? null) ? $resolved['sites'] : [];

        $sites = array_map(static function(array $siteEntry): array {
            $addresses = is_array($siteEntry['addresses'] ?? null) ? $siteEntry['addresses'] : [];

            $siteEntry['addresses'] = array_map(static function(array $address): array {
                $openingHours = is_array($address['openingHours'] ?? null) ? $address['openingHours'] : [];
                $address['openingHoursRows'] = array_map(
                    static fn(string $value): array => OrgSchemaService::parseOpeningHoursString($value),
                    $openingHours
                );

                return $address;
            }, $addresses);

            $logoAssetId = $siteEntry['logoAssetId'] ?? null;
            $logoAsset = $logoAssetId ? Craft::$app->getAssets()->getAssetById((int)$logoAssetId) : null;

            $siteEntry['logoSelectHtml'] = Cp::elementSelectHtml([
                'name' => 'orgSchema[' . $siteEntry['siteHandle'] . '][logoAssetId]',
                'elementType' => Asset::class,
                'criteria' => ['kind' => 'image'],
                'single' => true,
                'limit' => 1,
                'elements' => $logoAsset ? [$logoAsset] : [],
                'selectionLabel' => Craft::t('app', 'Choose'),
            ]);

            return $siteEntry;
        }, $sites);

        $firstSite = $sites[0] ?? null;

        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);
            $view->registerAssetBundle(OrgSchemaAsset::class);

            return $view->renderTemplate('toolkit/utilities/org-schema', [
                'sites' => $sites,
                'firstSite' => $firstSite,
                'types' => OrgSchemaService::TYPES,
                'conditionalFields' => OrgSchemaService::CONDITIONAL_FIELDS,
                'conditionalFieldsJson' => JsonHelper::encode(OrgSchemaService::CONDITIONAL_FIELDS),
                'formActionUrl' => UrlHelper::actionUrl('toolkit/org-schema/save'),
                'redirectPath' => self::redirectPath(),
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
