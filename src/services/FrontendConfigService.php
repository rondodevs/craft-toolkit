<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\helpers\App;
use rondodevs\toolkit\Toolkit;
use rondodevs\toolkit\support\Normalize;
use rondodevs\toolkit\support\RequestSigner;
use rondodevs\toolkit\support\SettingsTable;
use yii\base\Component;
use yii\db\Schema;

/**
 * The frontend's runtime settings, edited in Toolkit → Frontend: the frontend (layer-craft 2.1+) reads them
 * from `actions/toolkit/frontend-api/config` at startup and every minute, and right away when they're saved here.
 * Its own env vars, when set, still win over them: the frontend only needs NUXT_PUBLIC_BACKEND_URL.
 *
 * It also learns Craft's address on the Docker network (internalUrl): when it answers with Craft's own public key,
 * the frontend sends its server-side requests there instead of through the public URL.
 */
class FrontendConfigService extends Component
{
    public const INTERNAL_URL_ENV = 'CRAFT_INTERNAL_URL';
    // set by the craft-php image: the port nginx listens on inside the container
    public const INTERNAL_PORT_ENV = 'CRAFT_INTERNAL_PORT';
    public const CONFIG_PATH = '/__toolkit/config';
    private const SWITCHES = ['dataCache', 'routeCache', 'cdnCache', 'redirects', 'llms'];

    private ?SettingsTable $table = null;

    private function table(): SettingsTable
    {
        return $this->table ??= new SettingsTable('{{%toolkit_frontend_settings}}', [
            'dataCache' => Schema::TYPE_BOOLEAN,
            'routeCache' => Schema::TYPE_BOOLEAN,
            'cdnCache' => Schema::TYPE_BOOLEAN,
            'routeCacheMaxAge' => Schema::TYPE_INTEGER,
            'routeCacheExclude' => Schema::TYPE_TEXT,
            'redirects' => Schema::TYPE_BOOLEAN,
            'llms' => Schema::TYPE_BOOLEAN,
            'internalUrl' => Schema::TYPE_STRING,
            'latestSettingsUpdate' => Schema::TYPE_DATETIME,
        ], 'frontend-settings.json');
    }

    public function getDefaultSettings(): array
    {
        return [
            // off in dev mode, like the purges: a local frontend shows every change right away
            'dataCache' => !App::devMode(),
            'routeCache' => false,
            'cdnCache' => false,
            'routeCacheMaxAge' => 86400,
            'routeCacheExclude' => '',
            'redirects' => true,
            'llms' => true,
            'internalUrl' => '$' . self::INTERNAL_URL_ENV,
        ];
    }

    public function getOverrides(): array
    {
        $row = $this->table()->first() ?? [];
        $overrides = [];

        foreach (self::SWITCHES as $key) {
            $overrides[$key] = Normalize::bool($row[$key] ?? null);
        }

        return $overrides + [
            'routeCacheMaxAge' => Normalize::positiveInt($row['routeCacheMaxAge'] ?? null),
            'routeCacheExclude' => Normalize::string($row['routeCacheExclude'] ?? null),
            'internalUrl' => Normalize::string($row['internalUrl'] ?? null),
            'latestSettingsUpdate' => $row['latestSettingsUpdate'] ?? null,
        ];
    }

    public function getResolvedSettings(): array
    {
        $defaults = $this->getDefaultSettings();
        $overrides = $this->getOverrides();
        $resolved = [];

        foreach (self::SWITCHES as $key) {
            $resolved[$key] = (bool)($overrides[$key] ?? $defaults[$key]);
        }

        return $resolved + [
            'routeCacheMaxAge' => $overrides['routeCacheMaxAge'] ?? $defaults['routeCacheMaxAge'],
            'routeCacheExclude' => $overrides['routeCacheExclude'] ?? $defaults['routeCacheExclude'],
            'internalUrl' => $overrides['internalUrl'] ?? $defaults['internalUrl'],
            'latestSettingsUpdate' => $overrides['latestSettingsUpdate'],
        ];
    }

    /**
     * Saves the settings and tells the frontend: a cache switched back on is flushed first, since while it was
     * off the frontend kept its old items. A frontend that can't be reached only logs a warning: it picks the
     * new settings up within a minute anyway.
     */
    public function saveSettings(array $settings): array
    {
        $before = $this->getResolvedSettings();
        $payload = ['latestSettingsUpdate' => SettingsTable::now()];

        foreach (self::SWITCHES as $key) {
            $payload[$key] = Normalize::bool($settings[$key] ?? null);
        }

        $maxAge = $settings['routeCacheMaxAge'] ?? null;

        if (Normalize::string($maxAge) !== null && Normalize::positiveInt($maxAge) === null) {
            throw new \InvalidArgumentException('Page cache max age must be a positive number of seconds.');
        }

        $internalUrl = Normalize::string($settings['internalUrl'] ?? null);

        $this->table()->put($payload + [
            'routeCacheMaxAge' => Normalize::positiveInt($maxAge),
            'routeCacheExclude' => Normalize::string(implode("\n", $this->parseExclude($settings['routeCacheExclude'] ?? null))),
            // `$VAR` or an absolute URL
            'internalUrl' => $internalUrl !== null && !str_starts_with($internalUrl, '$')
                ? Normalize::url($internalUrl, 'Internal URL')
                : $internalUrl,
        ]);

        $after = $this->getResolvedSettings();
        $reenabled = ($after['dataCache'] && !$before['dataCache']) || ($after['routeCache'] && !$before['routeCache']);
        $this->notifyFrontend($reenabled);

        return $after;
    }

    /**
     * What `actions/toolkit/frontend-api/config` answers: switches and Craft's addresses, nothing secret.
     */
    public function publicConfig(): array
    {
        $settings = $this->getResolvedSettings();

        return [
            'version' => 1,
            'updatedAt' => $settings['latestSettingsUpdate'],
            'cache' => [
                'data' => $settings['dataCache'],
                'route' => $settings['routeCache'],
                'cdn' => $settings['cdnCache'],
                'routeMaxAge' => (int)$settings['routeCacheMaxAge'],
                'routeExclude' => $this->parseExclude($settings['routeCacheExclude']),
            ],
            'redirects' => $settings['redirects'],
            'llms' => $settings['llms'],
            'internalUrl' => $this->internalUrl(),
            'publicKey' => RequestSigner::publicKey(),
        ];
    }

    /**
     * Craft's address on the Docker network: the setting (`$CRAFT_INTERNAL_URL` by default), otherwise
     * `http://<container hostname>:<CRAFT_INTERNAL_PORT>` when the image sets the port (craft-php), otherwise none.
     * The container's hostname is its short id, which other containers on the same network resolve.
     */
    public function internalUrl(): ?string
    {
        $url = Normalize::string(App::parseEnv((string)$this->getResolvedSettings()['internalUrl']));

        if ($url !== null && !str_starts_with($url, '$')) {
            return rtrim($url, '/');
        }

        $port = Normalize::positiveInt(App::env(self::INTERNAL_PORT_ENV));
        $host = gethostname();

        return $port !== null && $host ? "http://$host:$port" : null;
    }

    /**
     * Tells the frontend to read its settings again (signed, like the purges), after flushing its cache when asked.
     */
    public function notifyFrontend(bool $flush = false): void
    {
        $kvCache = Toolkit::getInstance()->kvCache;

        try {
            if ($flush) {
                $kvCache->flushAll();
            }

            $kvCache->postToFrontend(self::CONFIG_PATH, new \stdClass());
        } catch (\Throwable $e) {
            Craft::warning('FrontendConfigService: unable to notify the frontend - ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Path prefixes, one per line or comma separated, each starting with a slash.
     *
     * @return string[]
     */
    public function parseExclude(mixed $value): array
    {
        $items = is_array($value) ? $value : (preg_split('/[\r\n,]+/', (string)$value) ?: []);
        $paths = [];

        foreach ($items as $item) {
            $path = Normalize::path($item);

            if ($path !== null) {
                $paths[] = $path;
            }
        }

        return array_values(array_unique($paths));
    }
}
