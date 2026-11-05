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
    // the frontend's last report, kept for the purge jobs (how long Cloudflare waits after Nuxt)
    private const REPORT_CACHE_KEY = 'toolkit_frontend_health_report';
    private const REPORT_TTL = 600;

    /**
     * @return array{message: string, groups: array<int, array{label: string, checks: array}>}
     */
    public function run(): array
    {
        $frontend = $this->fetchFrontendHealth();
        $this->rememberReport($frontend);

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
            'ok' => $this->row('Health endpoint', 'ok', 'Reachable, and it accepts Craft\'s requests.'),
            'unauthorized' => $this->row('Health endpoint', 'fail', 'The frontend refuses Craft\'s requests: it could not check their signature (it fetches Craft\'s public key from NUXT_PUBLIC_BACKEND_URL, so that address must reach this Craft), and CACHE_API_TOKEN, if set, differs between the two services.'),
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
        $rows = [];

        $data = $frontend['data'];
        array_push($rows, ...$this->authChecks($data));

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

        array_push($rows, ...$this->multiCacheChecks($data, $purging, $kvCacheLink));

        return $rows;
    }

    /**
     * nuxt-multi-cache on the frontend (layer-craft 1.2 reports it): its version, a module token that
     * would refuse Craft's signed purges, how purges by tag run, the page cache and the tag registry.
     */
    private function multiCacheChecks(array $data, bool $purging, array $kvCacheLink): array
    {
        $multiCache = $data['multiCache'] ?? null;

        // layer-craft before 1.2: nothing more to compare
        if (!is_array($multiCache)) {
            return [];
        }

        $rows = [];
        $version = $multiCache['version'] ?? null;
        $major = $version ? (int)explode('.', (string)$version)[0] : null;

        $rows[] = match (true) {
            $major === null => $this->row('nuxt-multi-cache', 'skip', 'The frontend did not report its version.'),
            $major < 5 => $this->row('nuxt-multi-cache', 'warn', "Version $version: update @studio-fes/layer-craft, which brings nuxt-multi-cache 5 (synchronous purges, page cache, tag registry)."),
            default => $this->row('nuxt-multi-cache', 'ok', "Version $version."),
        };

        if ($multiCache['moduleTokenSet'] ?? false) {
            $rows[] = $this->row('Module token', 'fail', 'NUXT_MULTI_CACHE_API_AUTHORIZATION_TOKEN is set on the frontend: nuxt-multi-cache requires it on every purge, and Craft\'s signed requests don\'t send it, so purges get 401. Remove it: the layer already protects the cache API.');
        }

        $delay = $multiCache['cacheTagInvalidationDelay'] ?? null;
        if (is_int($delay)) {
            $rows[] = $delay === 0
                ? $this->row('Tag purges', 'ok', 'Applied during the request: Cloudflare is purged right after the frontend.')
                : $this->row('Tag purges', 'warn', 'Applied ' . round($delay / 1000, 1) . 's after the request (cacheTagInvalidationDelay): Cloudflare waits for them. With the page cache on, set it to 0, as @studio-fes/layer-craft 1.2 does.');
        }

        $routeCache = $data['routeCache'] ?? null;
        if (is_array($routeCache)) {
            $driver = $data['dataCache']['driver'] ?? 'none';
            $maxAge = $routeCache['maxAge'] ?? null;
            $kept = $maxAge ? ' Pages are kept ' . round($maxAge / 3600, 1) . 'h at most.' : '';

            $rows[] = match (true) {
                !($routeCache['enabled'] ?? false) => $this->row('Page cache', 'skip', 'Off: pages are rendered on every request, from cached data. NUXT_MULTI_CACHE_ROUTE=true on the frontend serves their HTML from the cache, purged on save like the data.'),
                !$purging => $this->row('Page cache', 'fail', 'On, but Craft does not purge it on save: edits show up only when pages expire.' . $kept, $kvCacheLink),
                $driver === 'fs' => $this->row('Page cache', 'warn', 'On, on the frontend\'s disk: fine for a single instance, emptied on every deploy. With more instances set NUXT_REDIS_URL, or purges reach one of them only.' . $kept),
                default => $this->row('Page cache', 'ok', "On ($driver), purged on save." . $kept),
            };
        }

        if (($data['dataCache']['driver'] ?? null) === 'redis') {
            $rows[] = ($data['cacheTagRegistry'] ?? false)
                ? $this->row('Tag registry', 'ok', 'On Redis: a purge by tag only touches the items with that tag.')
                : $this->row('Tag registry', 'warn', 'None: every purge by tag reads the whole cache, slower as it grows. Update @studio-fes/layer-craft.');
        }

        return $rows;
    }

    /**
     * How long, in milliseconds, the frontend waits before applying a purge by tag
     * (nuxt-multi-cache's cacheTagInvalidationDelay), from its last report: 0 means the purge is
     * done when its request returns. `null` when unknown (older layer, frontend unreachable).
     */
    public function frontendTagPurgeDelay(): ?int
    {
        $report = Craft::$app->getCache()->get(self::REPORT_CACHE_KEY);

        if ($report === false) {
            $report = $this->rememberReport($this->fetchFrontendHealth());
        }

        $delay = $report['data']['multiCache']['cacheTagInvalidationDelay'] ?? null;

        return is_int($delay) ? $delay : null;
    }

    private function rememberReport(array $frontend): array
    {
        // only what the purge jobs need, and a failed fetch is kept too: it's retried after the TTL
        $report = ['data' => $frontend['data'] === null ? null : ['multiCache' => $frontend['data']['multiCache'] ?? null]];
        Craft::$app->getCache()->set(self::REPORT_CACHE_KEY, $report, self::REPORT_TTL);

        return $report;
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
     * How the frontend let Craft in: a signature checked with Craft's public key needs nothing
     * configured (layer-craft 1.2); a token has to match on both sides.
     */
    private function authChecks(?array $data): array
    {
        $kvCache = Toolkit::getInstance()->kvCache;
        $kvCacheLink = ['url' => ToolkitSections::url('kv-cache'), 'label' => 'KV Cache settings'];
        $env = KvCacheService::AUTH_TOKEN_ENV;
        $tokenSource = $kvCache->getAuthToken()['source'];
        $signatureError = $data['signature']['error'] ?? null;
        $rows = [];

        if ($data === null) {
            return [];
        }

        $rows[] = match ($data['authorizedBy'] ?? null) {
            'signature' => $this->row('Authentication', 'ok', 'Craft signs its requests and the frontend checks them with Craft\'s public key: no token to configure.'),
            'token' => $signatureError
                ? $this->row('Authentication', 'warn', "With $env, since the frontend can't check signatures: $signatureError")
                : $this->row('Authentication', 'ok', "With $env."),
            'legacy-token' => $this->row('Authentication', 'warn', "With the old public default token, since the frontend can't check signatures: " . ($signatureError ?? 'unknown error.')),
            default => $this->row('Authentication', 'warn', "With a token: the frontend runs @studio-fes/layer-craft before 1.2, which can't check Craft's signatures. Update it, or give both services the same $env."),
        };

        if ($tokenSource === 'setting') {
            $rows[] = $this->row('Token (Craft)', 'warn', "Saved on the KV Cache screen, which overrides $env: clear the field unless the frontend needs that token.", $kvCacheLink);
        }

        // before 1.2 the frontend only has the token, and accepts the public default without one
        if (!isset($data['authorizedBy']) && !($data['cacheApiToken']['fromEnv'] ?? false)) {
            $rows[] = $this->row('Token (frontend)', 'warn', "$env is not set on the frontend: it accepts the old public default token, anyone can empty its cache.");
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
                'headers' => $kvCache->authHeaders('GET', $url . self::FRONTEND_HEALTH_PATH),
            ]);
            $data = json_decode((string)$response->getBody(), true);

            return ['status' => 'ok', 'data' => is_array($data) ? $data : null] + $result;
        } catch (RequestException $e) {
            $code = $e->getResponse()?->getStatusCode();

            return match ($code) {
                // a 403 is a proxy or firewall in the way (Cloudflare, Vite's allowed hosts), not the frontend's answer
                401 => ['status' => 'unauthorized'] + $result,
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
