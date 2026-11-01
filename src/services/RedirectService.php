<?php

namespace rondodevs\toolkit\services;

use craft\helpers\App;
use rondodevs\toolkit\support\Normalize;
use rondodevs\toolkit\support\SettingsTable;
use yii\base\Component;
use yii\db\Schema;

/**
 * Where the site root sends visitors in production: the frontend.
 */
class RedirectService extends Component
{
    private ?SettingsTable $table = null;

    private function table(): SettingsTable
    {
        return $this->table ??= new SettingsTable('{{%toolkit_redirect_settings}}', [
            'enabled' => Schema::TYPE_BOOLEAN,
            'frontendUrl' => Schema::TYPE_STRING,
            'latestSettingsUpdate' => Schema::TYPE_DATETIME,
        ], 'redirect-settings.json');
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
        $row = $this->table()->first() ?? [];

        return [
            'enabled' => Normalize::bool($row['enabled'] ?? null),
            'frontendUrl' => Normalize::string($row['frontendUrl'] ?? null),
            'latestSettingsUpdate' => $row['latestSettingsUpdate'] ?? null,
        ];
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
        $payload = [
            'enabled' => (bool)($settings['enabled'] ?? false),
            'frontendUrl' => Normalize::url($settings['frontendUrl'] ?? null, 'Frontend URL'),
            'latestSettingsUpdate' => SettingsTable::now(),
        ];

        $this->table()->put($payload);

        return $this->getResolvedSettings();
    }
}
