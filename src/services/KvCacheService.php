<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\helpers\App;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use rondodevs\toolkit\Toolkit;
use rondodevs\toolkit\jobs\PurgeCloudflareCacheJob;
use rondodevs\toolkit\jobs\PurgeKvCacheJob;
use rondodevs\toolkit\support\Normalize;
use rondodevs\toolkit\support\RequestSigner;
use rondodevs\toolkit\support\SettingsTable;
use yii\base\Component;
use yii\db\Schema;

class KvCacheService extends Component
{
    public const AUTH_TOKEN_ENV = 'CACHE_API_TOKEN';
    // nuxt-multi-cache's example token, the default before CACHE_API_TOKEN: to drop once every project sets it
    private const FALLBACK_AUTH_TOKEN = 'hunter';
    private const UNREACHABLE_CACHE_KEY = 'toolkit_kvcache_endpoint_unreachable';
    private const UNREACHABLE_TTL = 300;
    private const CLOUDFLARE_API = 'https://api.cloudflare.com/client/v4';
    // Cloudflare accepts at most 100 tags per purge request.
    private const CLOUDFLARE_MAX_TAGS = 100;
    // Cloudflare must be purged after the frontend, or it refetches a still-stale page from it (with the
    // page cache on, the stale HTML itself). A frontend with a cacheTagInvalidationDelay of 0
    // (layer-craft 1.2) has purged when its request returns: Cloudflare follows right away. Otherwise
    // it waits this long, or the frontend's delay plus a margin when longer.
    private const CLOUDFLARE_PURGE_DELAY = 10;
    private const CLOUDFLARE_PURGE_MARGIN = 5;
    // Cloudflare rate-limits purge requests: purges are buffered and sent together once this window
    // has passed, so a burst of saves becomes one request (or one per CLOUDFLARE_MAX_TAGS tags).
    private const CLOUDFLARE_BATCH_WINDOW = 60;
    private const CLOUDFLARE_PENDING_KEY = 'toolkit_cloudflare_pending_purge';
    private const CLOUDFLARE_PENDING_LOCK = 'toolkit-cloudflare-pending-purge';
    // a flush job not run after this long (queue cleared, worker down) no longer counts as queued
    private const STALE_JOB = 900;
    // Saving a page saves each of its nested (Matrix) entries too, every one with its own purge job:
    // their tags are buffered and sent to the frontend in one request once this window has passed.
    private const KV_BATCH_WINDOW = 5;
    private const KV_PENDING_KEY = 'toolkit_kvcache_pending_purge';
    private const KV_PENDING_LOCK = 'toolkit-kvcache-pending-purge';

    private ?SettingsTable $table = null;

    private function table(): SettingsTable
    {
        return $this->table ??= new SettingsTable('{{%toolkit_kv_cache_settings}}', [
            'enabled' => Schema::TYPE_BOOLEAN,
            'frontendUrl' => Schema::TYPE_STRING,
            'authToken' => Schema::TYPE_STRING,
            'authHeaderName' => Schema::TYPE_STRING,
            'purgeTagsPath' => Schema::TYPE_STRING,
            'flushAllPath' => Schema::TYPE_STRING,
            'requestTimeout' => Schema::TYPE_INTEGER,
            'connectTimeout' => Schema::TYPE_INTEGER,
            'latestSettingsUpdate' => Schema::TYPE_DATETIME,
        ], 'kv-cache-settings.json', laterColumns: [
            // Cloudflare's settings live here too: every automatic Cloudflare purge follows a KV purge
            'cloudflareEnabled' => Schema::TYPE_BOOLEAN,
            'cloudflareZoneId' => Schema::TYPE_STRING,
            'cloudflareApiToken' => Schema::TYPE_STRING,
        ]);
    }

    private function normalizeSettings(array $settings): array
    {
        return [
            'enabled' => Normalize::bool($settings['enabled'] ?? null),
            'frontendUrl' => Normalize::string($settings['frontendUrl'] ?? null),
            'authToken' => Normalize::string($settings['authToken'] ?? null),
            'authHeaderName' => Normalize::string($settings['authHeaderName'] ?? null),
            'purgeTagsPath' => Normalize::path($settings['purgeTagsPath'] ?? null),
            'flushAllPath' => Normalize::path($settings['flushAllPath'] ?? null),
            'requestTimeout' => Normalize::int($settings['requestTimeout'] ?? null),
            'connectTimeout' => Normalize::int($settings['connectTimeout'] ?? null),
            'cloudflareEnabled' => Normalize::bool($settings['cloudflareEnabled'] ?? null),
            'cloudflareZoneId' => Normalize::string($settings['cloudflareZoneId'] ?? null),
            'cloudflareApiToken' => Normalize::string($settings['cloudflareApiToken'] ?? null),
            'latestSettingsUpdate' => $settings['latestSettingsUpdate'] ?? null,
        ];
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
            // the frontend (layer-craft) reads the same variable: one shared variable for both on Coolify
            'authToken' => '$' . self::AUTH_TOKEN_ENV,
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
        return $this->normalizeSettings($this->table()->first() ?? []);
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

        $this->table()->put([
            // Unset switches stay null, so they keep following their defaults
            'enabled' => Normalize::bool($settings['enabled'] ?? null),
            'frontendUrl' => Normalize::url($settings['frontendUrl'] ?? null, 'Frontend URL'),
            'authToken' => Normalize::string($settings['authToken'] ?? null),
            'authHeaderName' => Normalize::string($settings['authHeaderName'] ?? null),
            'purgeTagsPath' => Normalize::path($settings['purgeTagsPath'] ?? null),
            'flushAllPath' => Normalize::path($settings['flushAllPath'] ?? null),
            'requestTimeout' => Normalize::positiveInt($settings['requestTimeout'] ?? null),
            'connectTimeout' => Normalize::positiveInt($settings['connectTimeout'] ?? null),
            'cloudflareEnabled' => Normalize::bool($settings['cloudflareEnabled'] ?? null),
            'cloudflareZoneId' => Normalize::string($settings['cloudflareZoneId'] ?? null),
            'cloudflareApiToken' => Normalize::string($settings['cloudflareApiToken'] ?? null),
            'latestSettingsUpdate' => SettingsTable::now(),
        ]);

        return $this->getResolvedSettings();
    }

    /**
     * After the CP saved settings the frontend reads: purges their tags, or everything with
     * `null`. Skipped while invalidation is off; a failure is only logged, the save stands.
     */
    public function purgeAfterSave(?array $tags): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        try {
            $tags === null ? $this->flushAll() : $this->purgeTags($tags);
        } catch (\Throwable $e) {
            Craft::warning('KvCacheService: unable to purge the frontend cache after a save - ' . $e->getMessage(), __METHOD__);
        }
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

    /**
     * Adds tags to the frontend purge buffer, sent by a single PurgeKvCacheJob KV_BATCH_WINDOW
     * seconds after the first of them: a save and its nested entries' saves become one request.
     * Purges from the CP (settings screens, manual purges) go through purgeTags() right away instead.
     */
    public function queuePurgeTags(array $tags): void
    {
        $tags = array_values(array_unique(array_filter(array_map(static fn($tag) => trim((string)$tag), $tags))));

        if ($tags === []) {
            return;
        }

        $this->withLock(self::KV_PENDING_LOCK, function() use ($tags): void {
            $pending = $this->getPendingKvPurge();
            $pending['tags'] = array_values(array_unique([...$pending['tags'], ...$tags]));

            if ($pending['queuedAt'] === null || time() - $pending['queuedAt'] > self::STALE_JOB) {
                Craft::$app->getQueue()->delay(self::KV_BATCH_WINDOW)->push(new PurgeKvCacheJob());
                $pending['queuedAt'] = time();
            }

            Craft::$app->getCache()->set(self::KV_PENDING_KEY, $pending);
        });
    }

    /**
     * Sends the buffered frontend purge, run by PurgeKvCacheJob. When the request fails the tags go
     * back in the buffer, to be sent with the next save's purge, and the job fails so the CP shows it.
     */
    public function flushPendingKvPurge(): void
    {
        $tags = $this->withLock(self::KV_PENDING_LOCK, function(): array {
            $tags = $this->getPendingKvPurge()['tags'];
            Craft::$app->getCache()->delete(self::KV_PENDING_KEY);

            return $tags;
        });

        if ($tags === []) {
            return;
        }

        try {
            $this->purgeTags($tags);
        } catch (\Throwable $e) {
            $this->withLock(self::KV_PENDING_LOCK, function() use ($tags): void {
                $pending = $this->getPendingKvPurge();
                $pending['tags'] = array_values(array_unique([...$tags, ...$pending['tags']]));
                Craft::$app->getCache()->set(self::KV_PENDING_KEY, $pending);
            });

            throw $e;
        }
    }

    private function getPendingKvPurge(): array
    {
        $pending = Craft::$app->getCache()->get(self::KV_PENDING_KEY);

        return [
            'tags' => $pending['tags'] ?? [],
            'queuedAt' => $pending['queuedAt'] ?? null,
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
                'headers' => $this->authHeaders('GET', $url),
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

    public function getCloudflareZoneId(): ?string
    {
        return Normalize::string(App::parseEnv($this->getResolvedSettings()['cloudflareZoneId']));
    }

    public function getCloudflareApiToken(): ?string
    {
        return Normalize::string(App::parseEnv($this->getResolvedSettings()['cloudflareApiToken']));
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
     * Adds a purge to the Cloudflare buffer, sent after the Nuxt one by a single delayed job together
     * with every other purge buffered meanwhile. `null` purges everything.
     * A failure here never fails the Nuxt purge: the edge cache still expires on its own max-age.
     */
    public function queueCloudflarePurge(?array $tags): void
    {
        if (!$this->isCloudflareEnabled()) {
            // logged so that a purge that never reaches Cloudflare can be told apart from a failed one
            Craft::info('KvCacheService: Cloudflare purge skipped - switched off, or Zone ID / API token missing.', __METHOD__);
            return;
        }

        try {
            $this->withLock(self::CLOUDFLARE_PENDING_LOCK, function() use ($tags): void {
                $pending = $this->getPendingCloudflarePurge();
                $pending['everything'] = $pending['everything'] || $tags === null;
                $pending['tags'] = $pending['everything'] ? [] : array_values(array_unique([...$pending['tags'], ...$tags]));
                $pending['updatedAt'] = time();

                if ($pending['queuedAt'] === null || time() - $pending['queuedAt'] > self::STALE_JOB) {
                    Craft::$app->getQueue()->delay(self::CLOUDFLARE_BATCH_WINDOW)->push(new PurgeCloudflareCacheJob());
                    $pending['queuedAt'] = time();
                }

                Craft::$app->getCache()->set(self::CLOUDFLARE_PENDING_KEY, $pending);
            });
        } catch (\Throwable $e) {
            Craft::error('KvCacheService: could not queue Cloudflare purge - ' . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Sends the buffered Cloudflare purge, run by PurgeCloudflareCacheJob. Waits again when a purge
     * was buffered less than cloudflarePurgeDelay() ago (its Nuxt purge may not be applied yet), and
     * puts the unsent tags back in the buffer when Cloudflare answers 429.
     */
    public function flushPendingCloudflarePurge(): void
    {
        // read before taking the lock: it may ask the frontend (Health report, at most every 10 minutes)
        $purgeDelay = $this->cloudflarePurgeDelay();

        $pending = $this->withLock(self::CLOUDFLARE_PENDING_LOCK, function() use ($purgeDelay): ?array {
            $pending = $this->getPendingCloudflarePurge();

            if (!$pending['everything'] && $pending['tags'] === []) {
                Craft::$app->getCache()->delete(self::CLOUDFLARE_PENDING_KEY);
                return null;
            }

            $wait = $pending['updatedAt'] + $purgeDelay - time();
            if ($wait > 0) {
                Craft::$app->getQueue()->delay($wait)->push(new PurgeCloudflareCacheJob());
                $pending['queuedAt'] = time();
                Craft::$app->getCache()->set(self::CLOUDFLARE_PENDING_KEY, $pending);
                return null;
            }

            Craft::$app->getCache()->delete(self::CLOUDFLARE_PENDING_KEY);
            return $pending;
        });

        if ($pending === null) {
            return;
        }

        $zoneId = $this->getCloudflareZoneId();
        $apiToken = $this->getCloudflareApiToken();

        if ($zoneId === null || $apiToken === null) {
            return;
        }

        $chunks = $pending['everything'] ? [null] : array_chunk($pending['tags'], self::CLOUDFLARE_MAX_TAGS);

        foreach ($chunks as $i => $chunk) {
            try {
                $this->sendCloudflarePurge($zoneId, $apiToken, $chunk === null ? ['purge_everything' => true] : ['tags' => $chunk]);
            } catch (RequestException $e) {
                if ($e->getResponse()?->getStatusCode() !== 429) {
                    throw $e;
                }

                $unsent = $chunk === null ? null : array_merge(...array_slice($chunks, $i));
                Craft::warning('KvCacheService: Cloudflare purge rate-limited, retrying in ' . self::CLOUDFLARE_BATCH_WINDOW . 's.', __METHOD__);
                $this->queueCloudflarePurge($unsent);
                return;
            }
        }

        Craft::info('KvCacheService: Cloudflare purge completed - ' . ($pending['everything'] ? 'everything' : implode(',', $pending['tags'])), __METHOD__);
    }

    /**
     * Seconds Cloudflare waits after the last frontend purge, from the frontend's purge delay
     * (Toolkit → Health reads it): none when the frontend purges during the request.
     */
    private function cloudflarePurgeDelay(): int
    {
        try {
            $delay = Toolkit::getInstance()->health->frontendTagPurgeDelay();
        } catch (\Throwable $e) {
            Craft::warning('KvCacheService: could not read the frontend purge delay - ' . $e->getMessage(), __METHOD__);
            $delay = null;
        }

        return match (true) {
            $delay === 0 => 0,
            $delay === null => self::CLOUDFLARE_PURGE_DELAY,
            default => max(self::CLOUDFLARE_PURGE_DELAY, (int)ceil($delay / 1000) + self::CLOUDFLARE_PURGE_MARGIN),
        };
    }

    private function getPendingCloudflarePurge(): array
    {
        $pending = Craft::$app->getCache()->get(self::CLOUDFLARE_PENDING_KEY);

        return [
            'tags' => $pending['tags'] ?? [],
            'everything' => $pending['everything'] ?? false,
            'updatedAt' => $pending['updatedAt'] ?? 0,
            'queuedAt' => $pending['queuedAt'] ?? null,
        ];
    }

    private function withLock(string $name, callable $callback): mixed
    {
        $mutex = Craft::$app->getMutex();

        if (!$mutex->acquire($name, 10)) {
            throw new \RuntimeException("Could not lock the purge buffer ($name).");
        }

        try {
            return $callback();
        } finally {
            $mutex->release($name);
        }
    }

    public function describeCloudflareError(\Throwable $e): string
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

    /**
     * The headers of a request to the frontend's cache API: Craft's signature (RequestSigner), which
     * the frontend checks with Craft's public key and needs no configuration, plus the token for a
     * frontend on a layer without signatures (layer-craft before 1.2).
     *
     * @return array<string, string>
     */
    public function authHeaders(string $method, string $url, string $body = ''): array
    {
        $settings = $this->getResolvedSettings();

        return [
            'Content-Type' => 'application/json',
            $settings['authHeaderName'] => $this->getAuthToken()['token'],
            ...RequestSigner::headers($method, $url, $body),
        ];
    }

    /**
     * The token sent to the frontend's cache API: the KV Cache setting, `$CACHE_API_TOKEN` by default.
     * Without it, the old default `hunter` for now, the same the frontend falls back to, so a project
     * updating one side only keeps purging. The Health screen flags anything but `env`.
     *
     * @return array{token: string, source: 'env'|'setting'|'fallback'}
     */
    public function getAuthToken(): array
    {
        $raw = (string)$this->getResolvedSettings()['authToken'];
        $token = Normalize::string(App::parseEnv($raw));

        if ($token === null) {
            return ['token' => self::FALLBACK_AUTH_TOKEN, 'source' => 'fallback'];
        }

        return ['token' => $token, 'source' => str_starts_with($raw, '$') ? 'env' : 'setting'];
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
        $json = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $client = $this->createHttpClient($settings);

        try {
            // signed over this exact body: the frontend hashes the raw body it receives
            $client->post($url, [
                'headers' => $this->authHeaders('POST', $url, $json),
                'body' => $json,
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
