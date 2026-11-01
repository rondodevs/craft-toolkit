<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\helpers\App;
use rondodevs\toolkit\support\Normalize;
use rondodevs\toolkit\support\SettingsTable;
use yii\base\Component;
use yii\db\Schema;

/**
 * Site name and URL for the frontend: env vars by default, overridable in the CP.
 */
class SiteConfigService extends Component
{
    private ?SettingsTable $table = null;

    private function table(): SettingsTable
    {
        return $this->table ??= new SettingsTable('{{%toolkit_site_config}}', [
            'siteName' => Schema::TYPE_STRING,
            'siteUrl' => Schema::TYPE_STRING,
            'latestSiteConfigUpdate' => Schema::TYPE_DATETIME,
        ], 'site-config.json');
    }

    public function getDefaultSiteConfig(): array
    {
        $currentSite = Craft::$app->getSites()->getCurrentSite();
        $siteName = App::env('CRAFT_SITE_NAME') ?: $currentSite->name;

        return [
            'siteName' => html_entity_decode(stripslashes($siteName), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'siteUrl' => App::env('CRAFT_FRONTEND_URL') ?: (string)$currentSite->baseUrl,
        ];
    }

    public function getOverrides(): array
    {
        $row = $this->table()->first() ?? [];

        return [
            'siteName' => $row['siteName'] ?? null,
            'siteUrl' => $row['siteUrl'] ?? null,
            'latestSiteConfigUpdate' => $row['latestSiteConfigUpdate'] ?? null,
        ];
    }

    public function getResolvedSiteConfig(): array
    {
        $defaults = $this->getDefaultSiteConfig();
        $overrides = $this->getOverrides();

        return [
            'siteName' => $overrides['siteName'] ?: $defaults['siteName'],
            'siteUrl' => $overrides['siteUrl'] ?: $defaults['siteUrl'],
            'latestSiteConfigUpdate' => $overrides['latestSiteConfigUpdate'],
        ];
    }

    public function saveOverrides(?string $siteName, ?string $siteUrl): array
    {
        $siteUrl = Normalize::string($siteUrl);

        if ($siteUrl !== null && filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Site URL must be a valid absolute URL.');
        }

        $payload = [
            'siteName' => Normalize::string($siteName),
            'siteUrl' => $siteUrl,
            'latestSiteConfigUpdate' => SettingsTable::now(),
        ];

        $this->table()->put($payload);

        return $this->getResolvedSiteConfig();
    }
}
