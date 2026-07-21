<?php

namespace rondodevs\toolkit\utilities;

use Craft;
use craft\base\Utility;
use craft\fields\Assets as AssetsField;
use craft\helpers\Json as JsonHelper;
use craft\helpers\UrlHelper;
use craft\models\Volume;
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

        $sites = array_map(static function (array $siteEntry): array {
            $addresses = is_array($siteEntry['addresses'] ?? null) ? $siteEntry['addresses'] : [];

            $siteEntry['addresses'] = array_map(static function (array $address): array {
                $openingHours = is_array($address['openingHours'] ?? null) ? $address['openingHours'] : [];
                $address['openingHoursRows'] = array_map(
                    static fn(string $value): array => OrgSchemaService::parseOpeningHoursString($value),
                    $openingHours
                );

                return $address;
            }, $addresses);

            return $siteEntry;
        }, $sites);

        $firstSite = $sites[0] ?? null;

        $view = Craft::$app->getView();
        $oldTemplateMode = $view->getTemplateMode();

        try {
            $view->setTemplateMode(View::TEMPLATE_MODE_CP);
            $view->registerAssetBundle(OrgSchemaAsset::class);

            $firstVolume = Craft::$app->getVolumes()->getAllVolumes()[0] ?? null;
            $sites = array_map(
                static fn(array $siteEntry): array => self::withLogoFieldHtml($siteEntry, $firstVolume),
                $sites
            );

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

    private static function withLogoFieldHtml(array $siteEntry, ?Volume $firstVolume): array
    {
        $logoAssetId = $siteEntry['logoAssetId'] ?? null;
        $siteEntry['logoAsset'] = $logoAssetId ? Craft::$app->getAssets()->getAssetById((int)$logoAssetId) : null;

        if ($firstVolume === null) {
            $siteEntry['logoFieldHtml'] = null;

            return $siteEntry;
        }

        $logoField = new AssetsField([
            'handle' => 'orgSchema[' . $siteEntry['siteHandle'] . '][logoAssetId]',
            'name' => Craft::t('app', 'Logo'),
            'sources' => '*',
            'viewMode' => 'large',
            'allowedKinds' => ['image'],
            'restrictFiles' => true,
            'maxRelations' => 1,
            'selectionLabel' => Craft::t('app', 'Choose'),
            'defaultUploadLocationSource' => 'volume:' . $firstVolume->uid,
        ]);

        $value = $logoField->normalizeValue($logoAssetId ? [(int)$logoAssetId] : [], null);
        $siteEntry['logoFieldHtml'] = $logoField->getInputHtml($value, null);

        return $siteEntry;
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
