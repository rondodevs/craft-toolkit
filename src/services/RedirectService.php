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

class RedirectService extends Component
{
    private const TABLE = '{{%toolkit_redirect_settings}}';

    private function ensureDbTable(): bool
    {
        $db = Craft::$app->getDb();

        if ($db->tableExists(self::TABLE)) {
            return true;
        }

        try {
            $db->createCommand()->createTable(self::TABLE, [
                'id' => Schema::TYPE_PK,
                'enabled' => Schema::TYPE_BOOLEAN,
                'frontendUrl' => Schema::TYPE_STRING,
                'latestSettingsUpdate' => Schema::TYPE_DATETIME,
                'dateCreated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'dateUpdated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'uid' => Schema::TYPE_CHAR . '(36) DEFAULT NULL',
            ])->execute();

            $db->createCommand()->createIndex('idx_toolkit_redirect_settings_uid', self::TABLE, 'uid', true)->execute();

            return true;
        } catch (\Throwable $e) {
            Craft::warning('Toolkit redirect settings table could not be created automatically: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    private function getStoragePath(): string
    {
        return Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'toolkit' . DIRECTORY_SEPARATOR . 'redirect-settings.json';
    }

    private function hasDbTable(): bool
    {
        return $this->ensureDbTable();
    }

    private function normalizeSettings(array $settings): array
    {
        return [
            'enabled' => array_key_exists('enabled', $settings) ? (bool)$settings['enabled'] : null,
            'frontendUrl' => $this->normalizeNullableString($settings['frontendUrl'] ?? null),
            'latestSettingsUpdate' => $settings['latestSettingsUpdate'] ?? null,
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }

    private function getFileOverrides(): array
    {
        $path = $this->getStoragePath();

        if (!file_exists($path)) {
            return $this->normalizeSettings([]);
        }

        $settings = json_decode(file_get_contents($path), true);

        if (!is_array($settings)) {
            return $this->normalizeSettings([]);
        }

        return $this->normalizeSettings($settings);
    }

    public function getDefaultSettings(): array
    {
        return [
            'enabled' => true,
            'frontendUrl' => rtrim((string)(App::env('CRAFT_FRONTEND_URL') ?: 'http://localhost:3000'), '/'),
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
                return $this->normalizeSettings([
                    'enabled' => $row['enabled'] ?? null,
                    'frontendUrl' => $row['frontendUrl'] ?? null,
                    'latestSettingsUpdate' => $row['latestSettingsUpdate'] ?? null,
                ]);
            }

            return $this->getFileOverrides();
        }

        return $this->getFileOverrides();
    }

    public function getResolvedSettings(): array
    {
        $defaults = $this->getDefaultSettings();
        $overrides = $this->getOverrides();

        return [
            'enabled' => $overrides['enabled'] ?? $defaults['enabled'],
            'frontendUrl' => $overrides['frontendUrl'] ?: $defaults['frontendUrl'],
            'latestSettingsUpdate' => $overrides['latestSettingsUpdate'],
        ];
    }

    public function isEnabled(): bool
    {
        return (bool)$this->getResolvedSettings()['enabled'];
    }

    public function saveSettings(array $settings): array
    {
        $enabled = (bool)($settings['enabled'] ?? false);
        $frontendUrl = rtrim(trim((string)($settings['frontendUrl'] ?? '')), '/');

        if ($frontendUrl !== '' && filter_var($frontendUrl, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Frontend URL must be a valid absolute URL.');
        }

        $payload = [
            'enabled' => $enabled,
            'frontendUrl' => $frontendUrl !== '' ? $frontendUrl : null,
            'latestSettingsUpdate' => (new DateTime('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:s'),
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

            return $this->getResolvedSettings();
        }

        $path = $this->getStoragePath();
        FileHelper::createDirectory(dirname($path));
        file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return $this->getResolvedSettings();
    }
}
