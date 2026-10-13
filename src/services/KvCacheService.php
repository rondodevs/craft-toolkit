<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\db\Query;
use craft\helpers\App;
use craft\helpers\FileHelper;
use DateTime;
use DateTimeZone;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use rondodevs\toolkit\jobs\PurgeCloudflareCacheJob;
use yii\base\Component;
use yii\db\Schema;

class KvCacheService extends Component
{
    private const TABLE = '{{%toolkit_kv_cache_settings}}';
    private const UNREACHABLE_CACHE_KEY = 'toolkit_kvcache_endpoint_unreachable';
    private const UNREACHABLE_TTL = 300;
    private const CLOUDFLARE_API = 'https://api.cloudflare.com/client/v4';
    // Cloudflare accepts at most 100 tags per purge request.
    private const CLOUDFLARE_MAX_TAGS = 100;
    // nuxt-multi-cache applies tag purges after its cacheTagInvalidationDelay (3s by default):
    // Cloudflare must be purged afterwards, or it refetches a still-stale page from the frontend.
    private const CLOUDFLARE_PURGE_DELAY = 10;
    // Columns added after the table was first released: created on existing installs by ensureDbTable().
    private const LATER_COLUMNS = [
        'cloudflareEnabled' => Schema::TYPE_BOOLEAN,
        'cloudflareZoneId' => Schema::TYPE_STRING,
        'cloudflareApiToken' => Schema::TYPE_STRING,
    ];

    private function ensureDbTable(): bool
    {
        $db = Craft::$app->getDb();

        if ($db->tableExists(self::TABLE)) {
            return $this->ensureLaterColumns();
        }

        try {
            $db->createCommand()->createTable(self::TABLE, [
                'id' => Schema::TYPE_PK,
                'enabled' => Schema::TYPE_BOOLEAN,
                'frontendUrl' => Schema::TYPE_STRING,
                'authToken' => Schema::TYPE_STRING,
                'authHeaderName' => Schema::TYPE_STRING,
                'purgeTagsPath' => Schema::TYPE_STRING,
                'flushAllPath' => Schema::TYPE_STRING,
                'requestTimeout' => Schema::TYPE_INTEGER,
                'connectTimeout' => Schema::TYPE_INTEGER,
                ...self::LATER_COLUMNS,
                'latestSettingsUpdate' => Schema::TYPE_DATETIME,
                'dateCreated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'dateUpdated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'uid' => Schema::TYPE_CHAR . '(36) DEFAULT NULL',
            ])->execute();

            $db->createCommand()->createIndex('idx_toolkit_kv_cache_settings_uid', self::TABLE, 'uid', true)->execute();

            return true;
        } catch (\Throwable $e) {
            Craft::warning('Toolkit KV cache settings table could not be created automatically: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    private function ensureLaterColumns(): bool
    {
        $db = Craft::$app->getDb();
        $missing = static fn($schema) => array_filter(
            array_keys(self::LATER_COLUMNS),
            static fn(string $name) => $schema && $schema->getColumn($name) === null,
        );

        // The cached schema answers the common case; it is refreshed only when it reports a gap.
        if ($missing($db->getTableSchema(self::TABLE)) === []) {
            return true;
        }

        try {
            foreach ($missing($db->getTableSchema(self::TABLE, true)) as $name) {
                $db->createCommand()->addColumn(self::TABLE, $name, self::LATER_COLUMNS[$name])->execute();
            }

            $db->getSchema()->refreshTableSchema(self::TABLE);

            return true;
        } catch (\Throwable $e) {
            Craft::warning('Toolkit KV cache settings columns could not be added automatically: ' . $e->getMessage(), __METHOD__);
            return false;
        }
    }

    private function getStoragePath(): string
    {
        return Craft::$app->getPath()->getStoragePath() . DIRECTORY_SEPARATOR . 'toolkit' . DIRECTORY_SEPARATOR . 'kv-cache-settings.json';
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
            'authToken' => $this->normalizeNullableString($settings['authToken'] ?? null),
            'authHeaderName' => $this->normalizeNullableString($settings['authHeaderName'] ?? null),
            'purgeTagsPath' => $this->normalizeNullablePath($settings['purgeTagsPath'] ?? null),
            'flushAllPath' => $this->normalizeNullablePath($settings['flushAllPath'] ?? null),
            'requestTimeout' => $this->normalizeNullableInt($settings['requestTimeout'] ?? null),
            'connectTimeout' => $this->normalizeNullableInt($settings['connectTimeout'] ?? null),
            'cloudflareEnabled' => isset($settings['cloudflareEnabled']) ? (bool)$settings['cloudflareEnabled'] : null,
            'cloudflareZoneId' => $this->normalizeNullableString($settings['cloudflareZoneId'] ?? null),
            'cloudflareApiToken' => $this->normalizeNullableString($settings['cloudflareApiToken'] ?? null),
            'latestSettingsUpdate' => $settings['latestSettingsUpdate'] ?? null,
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string)$value);
        return $value !== '' ? $value : null;
    }

    private function normalizeNullablePath(mixed $value): ?string
    {
        $value = trim((string)$value);

        if ($value === '') {
            return null;
        }

        return '/' . ltrim($value, '/');
    }

    private function normalizeNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int)$value;
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
            // Disabled by default in dev mode so local/staging environments don't
            // fire purge requests at a frontend they're not meant to control.
            // An explicitly saved override (via the KV Cache settings screen)
            // always takes precedence, so it can still be enabled manually.
            'enabled' => !App::devMode(),
            'frontendUrl' => rtrim((string)(App::env('FRONTEND_URL') ?: App::env('CRAFT_FRONTEND_URL') ?: ''), '/'),
            'authToken' => 'hunter',
            'authHeaderName' => 'x-nuxt-multi-cache-token',
            'purgeTagsPath' => '/__nuxt_multi_cache/purge/tags',
            'flushAllPath' => '/__nuxt_multi_cache/purge/all',
            'requestTimeout' => 5,
            'connectTimeout' => 2,
            // Same as `enabled`: off in dev mode, so a local install never purges the production zone.
            'cloudflareEnabled' => !App::devMode()
                && App::env('CLOUDFLARE_ZONE_ID')
                && App::env('CLOUDFLARE_API_TOKEN'),
            'cloudflareZoneId' => '$CLOUDFLARE_ZONE_ID',
            'cloudflareApiToken' => '$CLOUDFLARE_API_TOKEN',
            'latestSettingsUpdate' => null,
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
                    'authToken' => $row['authToken'] ?? null,
                    'authHeaderName' => $row['authHeaderName'] ?? null,
                    'purgeTagsPath' => $row['purgeTagsPath'] ?? null,
                    'flushAllPath' => $row['flushAllPath'] ?? null,
                    'requestTimeout' => $row['requestTimeout'] ?? null,
                    'connectTimeout' => $row['connectTimeout'] ?? null,
                    'cloudflareEnabled' => $row['cloudflareEnabled'] ?? null,
                    'cloudflareZoneId' => $row['cloudflareZoneId'] ?? null,
                    'cloudflareApiToken' => $row['cloudflareApiToken'] ?? null,
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
            'authToken' => $overrides['authToken'] ?: $defaults['authToken'],
            'authHeaderName' => $overrides['authHeaderName'] ?: $defaults['authHeaderName'],
            'purgeTagsPath' => $overrides['purgeTagsPath'] ?: $defaults['purgeTagsPath'],
            'flushAllPath' => $overrides['flushAllPath'] ?: $defaults['flushAllPath'],
            'requestTimeout' => $overrides['requestTimeout'] ?? $defaults['requestTimeout'],
            'connectTimeout' => $overrides['connectTimeout'] ?? $defaults['connectTimeout'],
            'cloudflareEnabled' => (bool)($overrides['cloudflareEnabled'] ?? $defaults['cloudflareEnabled']),
            'cloudflareZoneId' => $overrides['cloudflareZoneId'] ?: $defaults['cloudflareZoneId'],
            'cloudflareApiToken' => $overrides['cloudflareApiToken'] ?: $defaults['cloudflareApiToken'],
            'latestSettingsUpdate' => $overrides['latestSettingsUpdate'],
        ];
    }

    public function isEnabled(): bool
    {
        return (bool)$this->getResolvedSettings()['enabled'];
    }

    /**
     * Whether the frontend cache endpoint was recently found to be unreachable.
     * Used to avoid flooding the queue with jobs doomed to fail while the
     * frontend is down; the flag self-clears after UNREACHABLE_TTL seconds
     * so purges automatically resume once the endpoint comes back.
     */
    public function isEndpointUnreachable(): bool
    {
        return Craft::$app->getCache()->get(self::UNREACHABLE_CACHE_KEY) !== false;
    }

    public function getUnreachableReason(): ?string
    {
        $reason = Craft::$app->getCache()->get(self::UNREACHABLE_CACHE_KEY);
        return $reason !== false ? $reason : null;
    }

    private function markEndpointUnreachable(string $reason): void
    {
        Craft::$app->getCache()->set(self::UNREACHABLE_CACHE_KEY, $reason, self::UNREACHABLE_TTL);
    }

    private function markEndpointReachable(): void
    {
        Craft::$app->getCache()->delete(self::UNREACHABLE_CACHE_KEY);
    }

    /**
     * Saves the given settings; keys left out keep their stored value, so the KV Cache and
     * Cloudflare screens can each save only their own fields.
     */
    public function saveSettings(array $settings): array
    {
        $settings += $this->getOverrides();
        // Unset switches stay null, so they keep following their defaults.
        $enabled = isset($settings['enabled']) ? (bool)$settings['enabled'] : null;
        $frontendUrl = rtrim(trim((string)($settings['frontendUrl'] ?? '')), '/');
        $authToken = trim((string)($settings['authToken'] ?? ''));
        $authHeaderName = trim((string)($settings['authHeaderName'] ?? ''));
        $purgeTagsPath = $this->normalizeNullablePath($settings['purgeTagsPath'] ?? null);
        $flushAllPath = $this->normalizeNullablePath($settings['flushAllPath'] ?? null);
        $requestTimeout = ($settings['requestTimeout'] !== null && $settings['requestTimeout'] !== '') ? (int)$settings['requestTimeout'] : null;
        $connectTimeout = ($settings['connectTimeout'] !== null && $settings['connectTimeout'] !== '') ? (int)$settings['connectTimeout'] : null;
        $cloudflareEnabled = isset($settings['cloudflareEnabled']) ? (bool)$settings['cloudflareEnabled'] : null;
        $cloudflareZoneId = $this->normalizeNullableString($settings['cloudflareZoneId'] ?? null);
        $cloudflareApiToken = $this->normalizeNullableString($settings['cloudflareApiToken'] ?? null);

        if ($frontendUrl !== '' && filter_var($frontendUrl, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Frontend URL must be a valid absolute URL.');
        }

        $payload = [
            'enabled' => $enabled,
            'frontendUrl' => $frontendUrl !== '' ? $frontendUrl : null,
            'authToken' => $authToken !== '' ? $authToken : null,
            'authHeaderName' => $authHeaderName !== '' ? $authHeaderName : null,
            'purgeTagsPath' => $purgeTagsPath,
            'flushAllPath' => $flushAllPath,
            'requestTimeout' => ($requestTimeout !== null && $requestTimeout > 0) ? $requestTimeout : null,
            'connectTimeout' => ($connectTimeout !== null && $connectTimeout > 0) ? $connectTimeout : null,
            'cloudflareEnabled' => $cloudflareEnabled,
            'cloudflareZoneId' => $cloudflareZoneId,
            'cloudflareApiToken' => $cloudflareApiToken,
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

    public function purgeTags(array $tags): array
    {
        $tags = array_values(array_unique(array_filter(array_map(static fn($tag) => trim((string)$tag), $tags))));

        if ($tags === []) {
            return [
                'success' => true,
                'message' => 'No cache tags to purge.',
            ];
        }

        $settings = $this->getResolvedSettings();
        $url = $this->buildEndpointUrl($settings['frontendUrl'], $settings['purgeTagsPath']);
        $this->sendJsonRequest($url, $settings, $tags);

        Craft::info('KvCacheService: tag purge completed - ' . implode(',', $tags), __METHOD__);
        $this->queueCloudflarePurge($tags);

        return [
            'success' => true,
            'message' => 'Tag purge completed.',
        ];
    }

    public function purgeKeys(string $cacheType, array $keys): array
    {
        $keys = array_values(array_unique(array_filter(array_map(static fn($key) => trim((string)$key), $keys))));

        if ($keys === []) {
            return [
                'success' => true,
                'message' => 'No cache keys to purge.',
            ];
        }

        $settings = $this->getResolvedSettings();
        $url = $this->buildEndpointUrl($settings['frontendUrl'], '/__nuxt_multi_cache/purge/' . $cacheType);
        $this->sendJsonRequest($url, $settings, $keys);

        Craft::info('KvCacheService: key purge completed - ' . implode(',', $keys), __METHOD__);

        return [
            'success' => true,
            'message' => 'Key purge completed.',
        ];
    }

    public function flushAll(): array
    {
        $result = $this->flushKv();
        $this->queueCloudflarePurge(null);

        return $result;
    }

    /**
     * Flushes only the Nuxt KV cache, leaving Cloudflare alone.
     */
    public function flushKv(): array
    {
        $settings = $this->getResolvedSettings();
        $url = $this->buildEndpointUrl($settings['frontendUrl'], $settings['flushAllPath']);
        $this->sendJsonRequest($url, $settings, new \stdClass());

        Craft::info('KvCacheService: full cache flush completed.', __METHOD__);

        return [
            'success' => true,
            'message' => 'Full cache flush completed.',
        ];
    }

    /**
     * Emergency purge of the whole Cloudflare zone, sent right away instead of through the queue
     * so that any error reaches the CP. Works whether or not Cloudflare purging is switched on.
     */
    public function purgeCloudflareEverything(): array
    {
        $zoneId = $this->getCloudflareZoneId();
        $apiToken = $this->getCloudflareApiToken();

        if ($zoneId === null || $apiToken === null) {
            return [
                'success' => false,
                'message' => 'Cloudflare Zone ID or API token is missing.',
            ];
        }

        try {
            $this->sendCloudflarePurge($zoneId, $apiToken, ['purge_everything' => true]);
        } catch (\Throwable $e) {
            Craft::error('KvCacheService: Cloudflare purge everything failed - ' . $e->getMessage(), __METHOD__);

            return [
                'success' => false,
                'message' => $this->describeCloudflareError($e),
            ];
        }

        Craft::info('KvCacheService: Cloudflare purge completed - everything', __METHOD__);

        return [
            'success' => true,
            'message' => 'Cloudflare cache purged.',
        ];
    }

    /**
     * Emergency purge of both caches, KV first: Cloudflare purged first would refetch stale pages.
     * The KV flush is immediate (no cacheTagInvalidationDelay), so Cloudflare can follow right away.
     */
    public function purgeEverything(): array
    {
        $this->flushKv();
        $result = $this->purgeCloudflareEverything();

        return [
            'success' => $result['success'],
            'message' => $result['success']
                ? 'KV cache and Cloudflare cache purged.'
                : 'KV cache flushed, but Cloudflare failed: ' . $result['message'],
        ];
    }

    public function checkStats(): array
    {
        $settings = $this->getResolvedSettings();

        $url = $this->buildEndpointUrl($settings['frontendUrl'], '/__nuxt_multi_cache/stats/data');

        try {
            $client = $this->createHttpClient($settings);
            $response = $client->get($url, [
                'headers' => $this->buildAuthHeaders($settings),
            ]);

            $payload = json_decode((string)$response->getBody(), true);

            if (!is_array($payload)) {
                return [
                    'success' => true,
                    'message' => 'Stats endpoint reachable.',
                    'details' => null,
                ];
            }

            return [
                'success' => true,
                'message' => 'Stats endpoint reachable.',
                'details' => $payload,
            ];
        } catch (RequestException $e) {
            $statusCode = $e->getResponse()?->getStatusCode();
            $message = $statusCode ? 'Stats endpoint returned HTTP ' . $statusCode . '.' : 'Stats endpoint is unreachable.';
            Craft::warning('KvCacheService stats check failed: ' . $e->getMessage(), __METHOD__);

            return [
                'success' => false,
                'message' => $message,
            ];
        } catch (\Throwable $e) {
            Craft::warning('KvCacheService stats check failed: ' . $e->getMessage(), __METHOD__);

            return [
                'success' => false,
                'message' => 'Stats endpoint is unreachable.',
            ];
        }
    }

    /**
     * Cloudflare purging is switched on from the Cloudflare screen and needs a zone ID
     * and an API token with the "Zone → Cache Purge" permission. Both settings accept `$ENV_VAR`
     * and default to CLOUDFLARE_ZONE_ID and CLOUDFLARE_API_TOKEN.
     */
    public function isCloudflareEnabled(): bool
    {
        return $this->getResolvedSettings()['cloudflareEnabled']
            && $this->getCloudflareZoneId() !== null
            && $this->getCloudflareApiToken() !== null;
    }

    private function getCloudflareZoneId(): ?string
    {
        return $this->normalizeNullableString(App::parseEnv($this->getResolvedSettings()['cloudflareZoneId']));
    }

    private function getCloudflareApiToken(): ?string
    {
        return $this->normalizeNullableString(App::parseEnv($this->getResolvedSettings()['cloudflareApiToken']));
    }

    /**
     * Checks zone ID and token by purging one URL that is never cached: the same call and
     * permission the real purges use, without touching any cached page. Works whether or not
     * Cloudflare purging is switched on.
     */
    public function verifyCloudflare(): array
    {
        $zoneId = $this->getCloudflareZoneId();
        $apiToken = $this->getCloudflareApiToken();

        if ($zoneId === null || $apiToken === null) {
            return [
                'success' => false,
                'message' => 'Zone ID or API token is missing.',
            ];
        }

        $frontendUrl = $this->getResolvedSettings()['frontendUrl'] ?: 'https://example.com';

        try {
            $this->sendCloudflarePurge($zoneId, $apiToken, ['files' => [rtrim($frontendUrl, '/') . '/__toolkit_cloudflare_check']]);
        } catch (\Throwable $e) {
            Craft::warning('KvCacheService Cloudflare check failed: ' . $e->getMessage(), __METHOD__);

            return [
                'success' => false,
                'message' => $this->describeCloudflareError($e),
            ];
        }

        return [
            'success' => true,
            'message' => 'Zone ID and token can purge the cache.',
        ];
    }

    /**
     * Queues the Cloudflare purge for after the Nuxt one. `null` purges everything.
     * A failure here never fails the Nuxt purge: the edge cache still expires on its own max-age.
     */
    private function queueCloudflarePurge(?array $tags): void
    {
        if (!$this->isCloudflareEnabled()) {
            // logged so that a purge that never reaches Cloudflare can be told apart from a failed one
            Craft::info('KvCacheService: Cloudflare purge skipped - switched off, or Zone ID / API token missing.', __METHOD__);
            return;
        }

        try {
            Craft::$app->getQueue()->delay(self::CLOUDFLARE_PURGE_DELAY)->push(new PurgeCloudflareCacheJob([
                'tags' => $tags ?? [],
                'everything' => $tags === null,
            ]));
        } catch (\Throwable $e) {
            Craft::error('KvCacheService: could not queue Cloudflare purge - ' . $e->getMessage(), __METHOD__);
        }
    }

    public function purgeCloudflare(array $tags, bool $everything = false): void
    {
        $zoneId = $this->getCloudflareZoneId();
        $apiToken = $this->getCloudflareApiToken();

        if ($zoneId === null || $apiToken === null) {
            return;
        }

        $bodies = $everything
            ? [['purge_everything' => true]]
            : array_map(static fn(array $chunk) => ['tags' => $chunk], array_chunk($tags, self::CLOUDFLARE_MAX_TAGS));

        foreach ($bodies as $body) {
            $this->sendCloudflarePurge($zoneId, $apiToken, $body);
        }

        Craft::info('KvCacheService: Cloudflare purge completed - ' . ($everything ? 'everything' : implode(',', $tags)), __METHOD__);
    }

    private function describeCloudflareError(\Throwable $e): string
    {
        if (!$e instanceof RequestException) {
            return 'Cloudflare API is unreachable.';
        }

        $payload = json_decode((string)$e->getResponse()?->getBody(), true);
        $error = $payload['errors'][0]['message'] ?? null;
        $statusCode = $e->getResponse()?->getStatusCode();

        return 'Cloudflare rejected the request' . ($statusCode ? ' (HTTP ' . $statusCode . ')' : '') . ($error ? ': ' . $error : '.');
    }

    private function sendCloudflarePurge(string $zoneId, string $apiToken, array $body): void
    {
        $client = new Client(['timeout' => 10, 'connect_timeout' => 5]);

        $client->post(self::CLOUDFLARE_API . '/zones/' . rawurlencode($zoneId) . '/purge_cache', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function buildEndpointUrl(?string $frontendUrl, ?string $path): string
    {
        if (!$frontendUrl) {
            throw new \RuntimeException('Frontend URL is missing.');
        }

        if (!$path) {
            throw new \RuntimeException('Endpoint path is missing.');
        }

        return rtrim($frontendUrl, '/') . '/' . ltrim($path, '/');
    }

    private function buildAuthHeaders(array $settings): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        if (!empty($settings['authToken'])) {
            $headers[$settings['authHeaderName']] = $settings['authToken'];
        }

        return $headers;
    }

    private function createHttpClient(array $settings): Client
    {
        return new Client([
            'timeout' => (int)$settings['requestTimeout'],
            'connect_timeout' => (int)$settings['connectTimeout'],
        ]);
    }

    private function sendJsonRequest(string $url, array $settings, mixed $body): void
    {
        $headers = $this->buildAuthHeaders($settings);
        $client = $this->createHttpClient($settings);

        try {
            $client->post($url, [
                'headers' => $headers,
                'body' => json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
            $this->markEndpointReachable();
        } catch (ConnectException $e) {
            $this->markEndpointUnreachable($e->getMessage());
            Craft::error('KvCacheService request failed (endpoint unreachable): ' . $e->getMessage(), __METHOD__);
            throw $e;
        } catch (\Throwable $e) {
            Craft::error('KvCacheService request failed: ' . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }
}
