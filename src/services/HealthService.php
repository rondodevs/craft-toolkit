<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\helpers\App;
use craft\helpers\UrlHelper;
use craft\queue\Queue;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use rondodevs\toolkit\Toolkit;
use rondodevs\toolkit\ToolkitSections;
use yii\base\Component;

/**
 * Toolkit → Health: checks that Craft, the frontend and Cloudflare are set up to work together
 * (same cache API token, same backend, cache and CDN on or off on both sides, queue running),
 * naming the variable to set for each problem.
 */
class HealthService extends Component
{
    /** The frontend's report of its own cache settings (layer-craft), behind CACHE_API_TOKEN */
    public const FRONTEND_HEALTH_PATH = '/__toolkit/health';
    // a job waiting longer than this past its delay: the worker is probably not running
    private const QUEUE_STUCK_AFTER = 300;

    /**
     * @return array{message: string, groups: array<int, array{label: string, checks: array}>}
     */
    public function run(): array
    {
        $frontend = $this->fetchFrontendHealth();

        $groups = [
            ['label' => 'Craft', 'checks' => $this->craftChecks()],
            ['label' => 'Queue', 'checks' => $this->queueChecks()],
            ['label' => 'Frontend', 'checks' => $this->frontendChecks($frontend)],
            ['label' => 'Cache and purge', 'checks' => $this->cacheChecks($frontend)],
            ['label' => 'Cloudflare', 'checks' => $this->cloudflareChecks($frontend)],
        ];

        $rows = array_merge(...array_column($groups, 'checks'));
        $failed = count(array_filter($rows, static fn(array $row) => $row['status'] === 'fail'));
        $warned = count(array_filter($rows, static fn(array $row) => $row['status'] === 'warn'));

        return [
            'message' => match (true) {
                $failed > 0 => "$failed problem(s), $warned warning(s).",
                $warned > 0 => "No problems, $warned warning(s).",
                default => 'Everything is set up correctly.',
            },
            'groups' => $groups,
        ];
    }

    private function craftChecks(): array
    {
        $general = Craft::$app->getConfig()->getGeneral();
        $rows = [];

        $environment = App::env('CRAFT_ENVIRONMENT');
        $rows[] = $this->row('Environment', 'ok', $environment ? "CRAFT_ENVIRONMENT is \"$environment\"." : 'CRAFT_ENVIRONMENT is not set.');

        $rows[] = App::devMode()
            ? $this->row('Dev mode', 'warn', 'On: fine locally, set CRAFT_DEV_MODE=false on staging and production. With dev mode on, frontend and Cloudflare purges are off unless switched on by hand.')
            : $this->row('Dev mode', 'ok', 'Off.');

        $rows[] = $general->allowAdminChanges && !App::devMode()
            ? $this->row('Admin changes', 'warn', 'Allowed outside dev mode: project config edited here is overwritten by the next deploy. Set CRAFT_ALLOW_ADMIN_CHANGES=false.')
            : $this->row('Admin changes', 'ok', $general->allowAdminChanges ? 'Allowed (dev mode).' : 'Not allowed.');

        $rows[] = $general->enableGraphqlCaching
            ? $this->row('GraphQL cache', 'ok', 'On.')
            : $this->row('GraphQL cache', 'warn', 'Off: every frontend cache miss runs the full query. Turn on enableGraphqlCaching in config/general.php.');

        $backendUrl = $this->backendUrl();
        $rows[] = App::env('CRAFT_BACKEND_URL')
            ? $this->row('Backend URL', 'ok', "CRAFT_BACKEND_URL is $backendUrl.")
            : $this->row('Backend URL', 'warn', "CRAFT_BACKEND_URL is not set: compared with the frontend as $backendUrl, this request's host.");

        return $rows;
    }

    private function queueChecks(): array
    {
        $queue = Craft::$app->getQueue();
        $link = ['url' => UrlHelper::cpUrl('utilities/queue-manager'), 'label' => 'Queue manager'];
        $rows = [];

        if (!$queue instanceof Queue) {
            return [$this->row('Queue', 'skip', 'Not Craft\'s database queue: not checked.')];
        }

        $failed = $queue->getTotalFailed();
        $rows[] = $failed > 0
            ? $this->row('Failed jobs', 'fail', "$failed failed job(s): purges or encodes among them did not run. Retry them once the cause is fixed.", $link)
            : $this->row('Failed jobs', 'ok', 'None.');

        $oldest = (new Query())
            ->from(Table::QUEUE)
            ->where(['fail' => false, 'dateReserved' => null])
            ->min('[[timePushed]] + [[delay]]');

        $late = $oldest ? time() - (int)$oldest : 0;
        $rows[] = $late > self::QUEUE_STUCK_AFTER
            ? $this->row('Worker', 'fail', 'A job has been waiting ' . round($late / 60) . ' minutes: the queue worker (the "worker" service) is probably not running, so purges are not sent.', $link)
            : $this->row('Worker', 'ok', $queue->getTotalWaiting() . ' job(s) waiting, none stuck.');

        return $rows;
    }

    private function frontendChecks(array $frontend): array
    {
        $kvCacheLink = ['url' => ToolkitSections::url('kv-cache'), 'label' => 'KV Cache settings'];

        if ($frontend['url'] === null) {
            return [$this->row('Frontend URL', 'fail', 'Not set: set CRAFT_FRONTEND_URL (the frontend\'s public URL), or the Frontend URL on the KV Cache screen.', $kvCacheLink)];
        }

        $rows = [$this->row('Frontend URL', 'ok', $frontend['url'] . '.')];

        $rows[] = match ($frontend['status']) {
            'ok' => $this->row('Health endpoint', 'ok', 'Reachable, and it accepts Craft\'s cache API token.'),
            'unauthorized' => $this->row('Health endpoint', 'fail', 'The frontend refuses Craft\'s cache API token: CACHE_API_TOKEN differs between the two services. Give both the same shared variable.'),
            'missing' => $this->row('Health endpoint', 'warn', 'Not found (' . self::FRONTEND_HEALTH_PATH . '): the frontend runs an older @studio-fes/layer-craft. Update it to check its settings from here.'),
            default => $this->row('Health endpoint', 'fail', 'Unreachable: ' . $frontend['error']),
        };

        if ($frontend['data'] === null) {
            return $rows;
        }

        $data = $frontend['data'];
        $frontendBackend = $this->host($data['backendUrl'] ?? null);
        $craftBackend = $this->host($this->backendUrl());

        $rows[] = match (true) {
            $frontendBackend === null => $this->row('Backend used by the frontend', 'fail', 'The frontend has no NUXT_PUBLIC_BACKEND_URL.'),
            $frontendBackend !== $craftBackend => $this->row('Backend used by the frontend', 'fail', "The frontend reads from $frontendBackend, not from this Craft ($craftBackend): check NUXT_PUBLIC_BACKEND_URL."),
            default => $this->row('Backend used by the frontend', 'ok', "$frontendBackend, this Craft."),
        };

        $frontendBase = $this->host($data['baseUrl'] ?? null);
        if ($frontendBase !== null && $frontendBase !== $this->host($frontend['url'])) {
            $rows[] = $this->row('Frontend base URL', 'warn', "NUXT_PUBLIC_BASE_URL is $frontendBase, the Frontend URL here is " . $this->host($frontend['url']) . ': canonical URLs and purges point to different hosts.');
        }

        return $rows;
    }

    private function cacheChecks(array $frontend): array
    {
        $kvCache = Toolkit::getInstance()->kvCache;
        $kvCacheLink = ['url' => ToolkitSections::url('kv-cache'), 'label' => 'KV Cache settings'];
        $env = KvCacheService::AUTH_TOKEN_ENV;
        $rows = [];

        $rows[] = match ($kvCache->getAuthToken()['source']) {
            'env' => $this->row('Token (Craft)', 'ok', "From $env."),
            'setting' => $this->row('Token (Craft)', 'warn', "Saved on the KV Cache screen, which overrides $env: clear the field to use the shared variable.", $kvCacheLink),
            default => $this->row('Token (Craft)', 'warn', "$env is not set: Craft sends the old public default token. Set $env, the same shared variable as the frontend."),
        };

        $data = $frontend['data'];

        if ($data !== null) {
            $rows[] = ($data['cacheApiToken']['fromEnv'] ?? false)
                ? $this->row('Token (frontend)', 'ok', "From $env.")
                : $this->row('Token (frontend)', 'warn', "$env is not set on the frontend: it accepts the old public default token, anyone can empty its cache.");
        }

        if ($frontend['url'] !== null) {
            $stats = $kvCache->checkStats();
            $rows[] = $stats['success']
                ? $this->row('Purge endpoint', 'ok', 'Craft can reach the frontend\'s purge API.')
                : $this->row('Purge endpoint', 'fail', $stats['message'] . ' Purges on save do not reach the frontend.', $kvCacheLink);
        }

        if ($data === null) {
            return $rows;
        }

        $frontendCache = (bool)($data['dataCache']['enabled'] ?? false);
        $driver = $data['dataCache']['driver'] ?? 'none';
        $purging = $kvCache->isEnabled();

        $rows[] = $frontendCache
            ? $this->row('Frontend data cache', 'ok', "On ($driver).")
            : $this->row('Frontend data cache', 'warn', 'Off: every page asks Craft for its data. Set NUXT_CACHE_ENABLED=true (or NUXT_REDIS_URL) on the frontend.');

        $rows[] = match (true) {
            $frontendCache && !$purging => $this->row('Purge on save', 'fail', 'The frontend caches with no expiry, but Craft does not purge it on save: edits never show up. Switch purging on (it is off in dev mode by default).', $kvCacheLink),
            !$frontendCache && $purging => $this->row('Purge on save', 'warn', 'Craft purges on save, but the frontend caches nothing yet.'),
            $purging => $this->row('Purge on save', 'ok', 'On, on both sides.'),
            default => $this->row('Purge on save', 'skip', 'No cache, no purges.'),
        };

        return $rows;
    }

    private function cloudflareChecks(array $frontend): array
    {
        $kvCache = Toolkit::getInstance()->kvCache;
        $cloudflareLink = ['url' => ToolkitSections::url('cloudflare'), 'label' => 'Cloudflare settings'];
        $hasCredentials = $kvCache->getCloudflareZoneId() !== null && $kvCache->getCloudflareApiToken() !== null;
        $purging = $kvCache->isCloudflareEnabled();
        $cdnHeaders = $frontend['data'] === null ? null : (bool)($frontend['data']['cdnCache'] ?? false);
        $rows = [];

        $rows[] = match (true) {
            $cdnHeaders === null => $this->row('Edge cache', 'skip', 'The frontend\'s settings could not be read.'),
            $cdnHeaders && !$purging => $this->row('Edge cache', 'fail', 'Cloudflare caches the pages (NUXT_CDN_CACHE_ENABLED), but Craft never purges it: set CLOUDFLARE_ZONE_ID and CLOUDFLARE_API_TOKEN on Craft, and switch purging on.', $cloudflareLink),
            !$cdnHeaders && $purging => $this->row('Edge cache', 'warn', 'Craft purges Cloudflare, but the frontend sends no CDN headers, so nothing is cached at the edge: set NUXT_CDN_CACHE_ENABLED=true on the frontend.'),
            $cdnHeaders => $this->row('Edge cache', 'ok', 'Cached at the edge and purged from Craft.'),
            default => $this->row('Edge cache', 'skip', 'Not used (NUXT_CDN_CACHE_ENABLED off, no Cloudflare purging).'),
        };

        if (!$hasCredentials) {
            return $rows;
        }

        $verify = $kvCache->verifyCloudflare();
        $rows[] = $verify['success']
            ? $this->row('Cloudflare API', 'ok', $verify['message'])
            : $this->row('Cloudflare API', 'fail', $verify['message'] . ' Check CLOUDFLARE_ZONE_ID and CLOUDFLARE_API_TOKEN (Zone → Cache Purge).', $cloudflareLink);

        if ($cdnHeaders || $purging) {
            // proxy, edge hits, previews never cached, Cache Rule: the Cloudflare screen's check
            foreach (Toolkit::getInstance()->cloudflareCacheRule->check()['checks'] as $row) {
                $rows[] = $row + ['link' => null];
            }
        }

        return $rows;
    }

    /**
     * @return array{url: ?string, status: 'ok'|'unauthorized'|'missing'|'error'|'skipped', data: ?array, error: ?string}
     */
    private function fetchFrontendHealth(): array
    {
        $kvCache = Toolkit::getInstance()->kvCache;
        $settings = $kvCache->getResolvedSettings();
        $url = $settings['frontendUrl'] ? rtrim($settings['frontendUrl'], '/') : null;
        $result = ['url' => $url, 'status' => 'skipped', 'data' => null, 'error' => null];

        if ($url === null) {
            return $result;
        }

        try {
            $response = (new Client(['timeout' => 10, 'connect_timeout' => 5]))->get($url . self::FRONTEND_HEALTH_PATH, [
                'headers' => [$settings['authHeaderName'] => $kvCache->getAuthToken()['token']],
            ]);
            $data = json_decode((string)$response->getBody(), true);

            return ['status' => 'ok', 'data' => is_array($data) ? $data : null] + $result;
        } catch (RequestException $e) {
            $code = $e->getResponse()?->getStatusCode();

            return match ($code) {
                401, 403 => ['status' => 'unauthorized'] + $result,
                404 => ['status' => 'missing'] + $result,
                default => ['status' => 'error', 'error' => $code ? "HTTP $code." : $e->getMessage()] + $result,
            };
        } catch (\Throwable $e) {
            return ['status' => 'error', 'error' => $e->getMessage()] + $result;
        }
    }

    private function backendUrl(): string
    {
        return rtrim((string)(App::env('CRAFT_BACKEND_URL') ?: Craft::$app->getRequest()->getHostInfo()), '/');
    }

    private function host(?string $url): ?string
    {
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;

        return $host ? strtolower($host) : null;
    }

    private function row(string $label, string $status, string $message, ?array $link = null): array
    {
        return ['label' => $label, 'status' => $status, 'message' => $message, 'link' => $link];
    }
}
