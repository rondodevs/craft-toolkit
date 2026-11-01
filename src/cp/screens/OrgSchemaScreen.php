<?php

namespace rondodevs\toolkit\cp\screens;

use Craft;
use craft\elements\Asset;
use rondodevs\toolkit\services\OrgSchemaService;
use rondodevs\toolkit\Toolkit;

final class OrgSchemaScreen implements Screen
{
    public static function props(): array
    {
        $sites = Toolkit::getInstance()->orgSchema->getResolvedOrgSchema()['sites'];

        return [
            'types' => OrgSchemaService::TYPES,
            'conditionalFields' => OrgSchemaService::CONDITIONAL_FIELDS,
            'sites' => array_map(static fn(array $site) => [
                'siteHandle' => $site['siteHandle'],
                'siteName' => $site['siteName'],
                'latestOrgSchemaUpdate' => $site['latestOrgSchemaUpdate'],
                'entry' => [
                    'type' => $site['type'],
                    'name' => $site['name'],
                    'legalName' => $site['legalName'],
                    'url' => $site['url'],
                    'logo' => self::asset($site['logoAssetId']),
                    'description' => $site['description'],
                    'email' => $site['email'],
                    'telephone' => $site['telephone'],
                    'sameAs' => $site['sameAs'],
                    // Opening hours are stored compiled ("Mo-Fr 09:00-18:00"): back to rows to edit
                    'addresses' => array_map(static fn(array $address) => [
                        ...$address,
                        'openingHours' => array_map(OrgSchemaService::parseOpeningHoursString(...), $address['openingHours']),
                    ], $site['addresses']),
                ],
            ], $sites),
        ];
    }

    /**
     * An image as the CP shows it in a picker.
     *
     * @return array{id: int, title: string, thumbUrl: ?string}|null
     */
    public static function asset(?int $id): ?array
    {
        $asset = $id ? Craft::$app->getAssets()->getAssetById($id) : null;

        if (!$asset instanceof Asset) {
            return null;
        }

        return [
            'id' => $asset->id,
            'title' => (string)$asset->title,
            'thumbUrl' => Craft::$app->getAssets()->getThumbUrl($asset, 120, 120),
        ];
    }
}
