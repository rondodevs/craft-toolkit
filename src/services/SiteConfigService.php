<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\FileHelper;
use DateTime;
use DateTimeZone;
use yii\base\Component;
use yii\db\Schema;

class SiteConfigService extends Component
{
    private const TABLE = '{{%toolkit_site_config}}';

    private function ensureDbTable(): bool
    {
        $db = Craft::$app->getDb();

        if ($db->tableExists(self::TABLE)) {
            return true;
        }

        try {
            $db->createCommand()->createTable(self::TABLE, [
                'id' => Schema::TYPE_PK,
                'siteName' => Schema::TYPE_STRING,
                'siteUrl' => Schema::TYPE_STRING,
                'latestSiteConfigUpdate' => Schema::TYPE_DATETIME,
                'dateCreated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'dateUpdated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'uid' => Schema::TYPE_CHAR . '(36) DEFAULT NULL',
            ])->execute();

            $db->createCommand()->createIndex('idx_toolkit_site_config_uid', self::TABLE, 'uid', true)->execute();

            return true;
        } catch (\Throwable $e) {
            Craft::warning('Toolkit SiteConfig table could not be created automatically: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    private function getStoragePath(): string
    {
        return Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'toolkit' . DIRECTORY_SEPARATOR . 'site-config.json';
    }

    private function hasDbTable(): bool
    {
        return $this->ensureDbTable();
    }

    private function normalizeOverrides(array $overrides): array
    {
        return [
            'siteName' => $overrides['siteName'] ?? null,
            'siteUrl' => $overrides['siteUrl'] ?? null,
            'latestSiteConfigUpdate' => $overrides['latestSiteConfigUpdate'] ?? null,
        ];
    }

    private function getFileOverrides(): array
    {
        $path = $this->getStoragePath();

        if (!file_exists($path)) {
            return $this->normalizeOverrides([]);
        }

        $overrides = json_decode(file_get_contents($path), true);

        if (!is_array($overrides)) {
            return $this->normalizeOverrides([]);
        }

        return $this->normalizeOverrides($overrides);
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
        if ($this->hasDbTable()) {
            $row = (new Query())
                ->from(self::TABLE)
                ->orderBy(['id' => SORT_ASC])
                ->one();

            if (is_array($row)) {
                return $this->normalizeOverrides([
                    'siteName' => $row['siteName'] ?? null,
                    'siteUrl' => $row['siteUrl'] ?? null,
                    'latestSiteConfigUpdate' => $row['latestSiteConfigUpdate'] ?? null,
                ]);
            }

            // If table exists but has no rows, preserve previous behavior by reading legacy file overrides.
            return $this->getFileOverrides();
        }

        // Safe fallback while migration hasn't been applied yet.
        return $this->getFileOverrides();
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
        $siteName = trim((string)$siteName);
        $siteUrl = trim((string)$siteUrl);

        if ($siteUrl !== '' && filter_var($siteUrl, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Site URL must be a valid absolute URL.');
        }

        $payload = [
            'siteName' => $siteName !== '' ? $siteName : null,
            'siteUrl' => $siteUrl !== '' ? $siteUrl : null,
            'latestSiteConfigUpdate' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
        ];

        if ($this->hasDbTable()) {
            $db = Craft::$app->getDb();
            $exists = (new Query())
                ->from(self::TABLE)
                ->exists();

            if ($exists) {
                $db->createCommand()
                    ->update(self::TABLE, $payload)
                    ->execute();
            } else {
                $db->createCommand()
                    ->insert(self::TABLE, $payload)
                    ->execute();
            }

            return $this->getResolvedSiteConfig();
        }

        // Safe fallback while migration hasn't been applied yet.
        $path = $this->getStoragePath();
        FileHelper::createDirectory(dirname($path));
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $this->getResolvedSiteConfig();
    }
}
