<?php

namespace rondodevs\toolkit\services;

use Craft;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\TransferStats;
use rondodevs\toolkit\Toolkit;
use yii\base\Component;

/**
 * Checks the edge cache from outside (the frontend's CDN headers and Cloudflare's cf-cache-status)
 * and, with a token that can read Cache Rules, the zone's Cache Rule; creates or fixes that rule.
 * The rule is the one described in the README: HTML and _payload.json cached per the frontend's
 * CDN-Cache-Control, with the purge API, server API routes and Craft previews left out.
 */
class CloudflareCacheRuleService extends Component
{
    public const RULE_DESCRIPTION = 'HTML e payload';
    private const CLOUDFLARE_API = 'https://api.cloudflare.com/client/v4';
    private const PHASE = 'http_request_cache_settings';
    private const EXCLUDED_PATHS = ['/__nuxt_multi_cache/', '/api/'];
    private const EXCLUDED_QUERIES = ['x-craft-preview', 'x-craft-live-preview', 'token='];
    private const CACHED_STATUSES = ['HIT', 'STALE', 'UPDATING', 'REVALIDATED'];

    /**
     * Runs every check and returns them as rows of `label`, `status` (ok, warn, fail or skip)
     * and `message`, plus `ruleAction`: `create` when the zone has no rule for the host, `fix`
     * when it has a wrong one, null when there is nothing the CP can do.
     */
    public function check(): array
    {
        $frontendUrl = Toolkit::getInstance()->kvCache->getResolvedSettings()['frontendUrl'];

        if (!$frontendUrl) {
            return $this->report([$this->row('Frontend', 'fail', 'Frontend URL is missing: set it on the KV cache screen.')], null);
        }

        try {
            $first = $this->probe($frontendUrl);
        } catch (\Throwable $e) {
            Craft::warning('CloudflareCacheRuleService: frontend probe failed - ' . $e->getMessage(), __METHOD__);

            return $this->report([$this->row('Frontend', 'fail', $frontendUrl . ' is unreachable.')], null);
        }

        $rows = [$this->checkCdnHeaders($first)];

        if ($first['cfRay'] === null) {
            $rows[] = $this->row('Cloudflare proxy', 'fail', $first['host'] . ' is not served through Cloudflare: its DNS record is "DNS only", or the Frontend URL points to the origin. Without the proxy the edge cache is not used.');
            $rows[] = $this->row('Cache Rule', 'skip', 'Checked once the site is proxied.');

            return $this->report($rows, null);
        }

        $rows[] = $this->row('Cloudflare proxy', 'ok', $first['host'] . ' is served through Cloudflare.');
        $rows[] = $this->checkEdgeHit($first);
        $rows[] = $this->checkPreviewsBypass($first['url']);

        [$ruleRow, $ruleAction] = $this->checkRule($first['host']);
        $rows[] = $ruleRow;

        return $this->report($rows, $ruleAction);
    }

    /**
     * Creates the Cache Rule for the frontend's host. A rule already there (same description,
     * or the first cache rule naming the host) is never duplicated: it is rewritten when wrong
     * and left alone when right. A new rule goes last, so it wins over the rules before it.
     */
    public function createRule(): array
    {
        $kvCache = Toolkit::getInstance()->kvCache;
        $zoneId = $kvCache->getCloudflareZoneId();
        $apiToken = $kvCache->getCloudflareApiToken();
        $frontendUrl = $kvCache->getResolvedSettings()['frontendUrl'];

        if ($zoneId === null || $apiToken === null) {
            return ['success' => false, 'message' => 'Zone ID or API token is missing.'];
        }

        if (!$frontendUrl) {
            return ['success' => false, 'message' => 'Frontend URL is missing.'];
        }

        try {
            $probe = $this->probe($frontendUrl);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $frontendUrl . ' is unreachable.'];
        }

        // the host after redirects (apex → www) is the one serving the pages; a host not behind
        // the proxy would most likely not belong to this zone
        if ($probe['cfRay'] === null) {
            return ['success' => false, 'message' => $probe['host'] . ' is not served through Cloudflare: proxy it first.'];
        }

        $rule = $this->buildRule($probe['host']);

        try {
            $ruleset = $this->getEntrypoint($zoneId, $apiToken);

            if ($ruleset === null) {
                $this->cloudflareRequest('POST', '/zones/' . rawurlencode($zoneId) . '/rulesets', $apiToken, [
                    'name' => 'default',
                    'kind' => 'zone',
                    'phase' => self::PHASE,
                    'rules' => [$rule],
                ]);
                $verb = 'created';
            } elseif ($existing = $this->findRule($ruleset['rules'] ?? [], $probe['host'])) {
                if (!$this->ruleProblems($existing, $probe['host'])) {
                    return ['success' => false, 'message' => 'The Cache Rule "' . ($existing['description'] ?? $existing['id']) . '" already exists and is set correctly: nothing changed.'];
                }

                $this->cloudflareRequest('PATCH', '/zones/' . rawurlencode($zoneId) . '/rulesets/' . rawurlencode($ruleset['id']) . '/rules/' . rawurlencode($existing['id']), $apiToken, $rule);
                $verb = 'updated';
            } else {
                $this->cloudflareRequest('POST', '/zones/' . rawurlencode($zoneId) . '/rulesets/' . rawurlencode($ruleset['id']) . '/rules', $apiToken, $rule);
                $verb = 'created';
            }
        } catch (\Throwable $e) {
            Craft::error('CloudflareCacheRuleService: could not write the Cache Rule - ' . $e->getMessage(), __METHOD__);

            return ['success' => false, 'message' => $this->describeError($e)];
        }

        Craft::info('CloudflareCacheRuleService: Cache Rule ' . $verb . ' for ' . $probe['host'], __METHOD__);

        return ['success' => true, 'message' => 'Cache Rule "' . self::RULE_DESCRIPTION . '" ' . $verb . ' for ' . $probe['host'] . '.'];
    }

    /**
     * Cloudflare's token template URL: opens the token form with the "Cache Purge" and
     * "Cache Rules → Edit" permissions and a name filled in. Template URLs only fill the form
     * for a new token: an existing one cannot be edited from a link. The zone is narrowed by hand.
     * https://developers.cloudflare.com/fundamentals/api/how-to/account-owned-token-template/
     */
    public function createTokenUrl(): string
    {
        return 'https://dash.cloudflare.com/profile/api-tokens?' . http_build_query([
            'permissionGroupKeys' => json_encode([
                ['key' => 'cache', 'type' => 'purge'],
                // the dashboard's key for "Zone → Cache Rules", not listed in the docs
                ['key' => 'cache_settings', 'type' => 'edit'],
            ]),
            'accountId' => '*',
            'zoneId' => 'all',
            'name' => 'Craft cache purge – ' . Craft::$app->getSites()->getPrimarySite()->getName(),
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The zone's Cache Rules in the order Cloudflare applies them, with their settings in the
     * dashboard's words. The rule the check uses for the frontend's host is marked, with what is
     * wrong in it, and so are the enabled rules after it, which can override its settings.
     */
    public function listRules(): array
    {
        $kvCache = Toolkit::getInstance()->kvCache;
        $zoneId = $kvCache->getCloudflareZoneId();
        $apiToken = $kvCache->getCloudflareApiToken();

        if ($zoneId === null || $apiToken === null) {
            return ['success' => false, 'message' => 'Zone ID or API token is missing.'];
        }

        try {
            $ruleset = $this->getEntrypoint($zoneId, $apiToken);
        } catch (RequestException $e) {
            if (in_array($e->getResponse()?->getStatusCode(), [401, 403], true)) {
                return [
                    'success' => false,
                    'message' => 'The API token cannot read Cache Rules: add the "Zone → Cache Rules → Edit" permission to it, or replace it with a token that has it.',
                    'tokenUrl' => $this->createTokenUrl(),
                ];
            }

            return ['success' => false, 'message' => $this->describeError($e)];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $this->describeError($e)];
        }

        $host = $this->frontendHost();
        $rules = $ruleset['rules'] ?? [];
        $ours = $host !== null ? $this->findRule($rules, $host) : null;
        $afterOurs = false;
        $rows = [];

        foreach ($rules as $index => $rule) {
            $isOurs = $ours !== null && ($rule['id'] ?? null) === $ours['id'];

            $rows[] = [
                'position' => $index + 1,
                'description' => $rule['description'] ?? '',
                'enabled' => $rule['enabled'] ?? true,
                'expression' => $rule['expression'] ?? '',
                'settings' => $this->describeSettings($rule),
                'isOurs' => $isOurs,
                'problems' => $isOurs ? $this->ruleProblems($rule, $host) : [],
                'overridesOurs' => $afterOurs && ($rule['enabled'] ?? true) && ($rule['action'] ?? null) === 'set_cache_settings',
            ];

            $afterOurs = $afterOurs || $isOurs;
        }

        return [
            'success' => true,
            'message' => count($rows) . ' Cache Rule(s).',
            'host' => $host,
            'rules' => $rows,
        ];
    }

    public function buildExpression(string $host): string
    {
        $parts = ['http.host eq "' . $host . '"'];

        foreach (self::EXCLUDED_PATHS as $path) {
            $parts[] = 'not starts_with(http.request.uri.path, "' . $path . '")';
        }

        foreach (self::EXCLUDED_QUERIES as $query) {
            $parts[] = 'not http.request.uri.query contains "' . $query . '"';
        }

        return '(' . implode(' and ', $parts) . ')';
    }

    /**
     * A rule's settings as label → value, worded as on the dashboard; settings the rule leaves
     * at their default are left out.
     */
    private function describeSettings(array $rule): array
    {
        $params = $rule['action_parameters'] ?? [];

        if (($rule['action'] ?? null) !== 'set_cache_settings') {
            return ['Action' => (string)($rule['action'] ?? 'unknown')];
        }

        $settings = [
            'Cache eligibility' => ($params['cache'] ?? null) === false ? 'Bypass cache' : 'Eligible for cache',
        ];

        if (isset($params['edge_ttl']['mode'])) {
            $settings['Edge TTL'] = match ($params['edge_ttl']['mode']) {
                'bypass_by_default' => 'Use cache-control header if present, bypass cache if not',
                'respect_origin' => 'Use cache-control header if present, use Cloudflare\'s default TTL if not',
                'override_origin' => 'Ignore cache-control header, ' . ($params['edge_ttl']['default'] ?? '?') . 's',
                default => $params['edge_ttl']['mode'],
            };
        }

        if (isset($params['browser_ttl']['mode'])) {
            $settings['Browser TTL'] = match ($params['browser_ttl']['mode']) {
                'respect_origin' => 'Respect origin TTL',
                'bypass' => 'Bypass cache',
                'override_origin' => 'Override origin, ' . ($params['browser_ttl']['default'] ?? '?') . 's',
                default => $params['browser_ttl']['mode'],
            };
        }

        if (!empty($params['cache_key']['custom_key']['query_string'])) {
            $settings['Cache key'] = 'Custom query string';
        }

        if (isset($params['serve_stale']['disable_stale_while_updating'])) {
            $settings['Serve stale'] = $params['serve_stale']['disable_stale_while_updating'] ? 'Off while updating' : 'On';
        }

        return $settings;
    }

    /**
     * The host that serves the frontend's pages, after redirects (apex → www); the Frontend URL's
     * own host when the frontend can't be reached.
     */
    private function frontendHost(): ?string
    {
        $frontendUrl = Toolkit::getInstance()->kvCache->getResolvedSettings()['frontendUrl'];

        if (!$frontendUrl) {
            return null;
        }

        try {
            return $this->probe($frontendUrl)['host'];
        } catch (\Throwable $e) {
            return parse_url($frontendUrl, PHP_URL_HOST) ?: null;
        }
    }

    private function buildRule(string $host): array
    {
        return [
            'description' => self::RULE_DESCRIPTION,
            'expression' => $this->buildExpression($host),
            'action' => 'set_cache_settings',
            'action_parameters' => [
                'cache' => true,
                // "Use cache-control header if present, bypass cache if not"
                'edge_ttl' => ['mode' => 'bypass_by_default'],
                'browser_ttl' => ['mode' => 'respect_origin'],
            ],
            'enabled' => true,
        ];
    }

    private function checkCdnHeaders(array $probe): array
    {
        $label = 'Frontend CDN cache';
        $cdnCacheControl = $probe['cdnCacheControl'];

        if ($cdnCacheControl === null) {
            return $this->row($label, 'fail', 'The home page has no CDN-Cache-Control header: set NUXT_CDN_CACHE_ENABLED=true on the frontend, with the data cache on (NUXT_REDIS_URL).');
        }

        if (preg_match('/private|no-store/i', $cdnCacheControl)) {
            return $this->row($label, 'warn', 'The home page is not cacheable (CDN-Cache-Control: ' . $cdnCacheControl . ', HTTP ' . $probe['status'] . '). Is it an error page?');
        }

        return $this->row($label, 'ok', 'CDN-Cache-Control: ' . $cdnCacheControl);
    }

    private function checkEdgeHit(array $first): array
    {
        $label = 'Edge cache of HTML';

        try {
            $second = $this->probe($first['url']);
        } catch (\Throwable $e) {
            return $this->row($label, 'fail', 'The second request to ' . $first['url'] . ' failed.');
        }

        $status = $second['cacheStatus'] ?? 'none';
        $statuses = 'cf-cache-status: ' . ($first['cacheStatus'] ?? 'none') . ', then ' . $status . '.';

        if (in_array($status, self::CACHED_STATUSES, true)) {
            return $this->row($label, 'ok', 'Cloudflare serves the page from its cache (' . $statuses . ')');
        }

        if ($second['cacheStatus'] === null) {
            return $this->row($label, 'fail', 'The response never went through Cloudflare\'s cache (no cf-cache-status): it is answered before the cache, by a Worker for example.');
        }

        if ($status === 'DYNAMIC') {
            return $this->row($label, 'fail', 'Cloudflare does not cache the HTML (' . $statuses . ') No Cache Rule makes it eligible for this host.');
        }

        if ($status === 'BYPASS') {
            $reason = $second['cdnCacheControl'] === null
                ? 'The frontend sends no CDN-Cache-Control, and the rule bypasses the cache without it.'
                : 'The page is private or sets a cookie, or a Cache Rule bypasses it.';

            return $this->row($label, 'fail', 'Cloudflare bypasses its cache (' . $statuses . ') ' . $reason);
        }

        return $this->row($label, 'warn', 'Not served from the cache yet (' . $statuses . ') Run the check again in a few seconds.');
    }

    /**
     * A preview request asked twice must never come back from the cache: a cached preview would
     * be served to visitors.
     */
    private function checkPreviewsBypass(string $url): array
    {
        $label = 'Craft previews';
        $previewUrl = $url . (str_contains($url, '?') ? '&' : '?') . 'x-craft-preview=toolkit-check';

        try {
            $this->probe($previewUrl);
            $status = $this->probe($previewUrl)['cacheStatus'] ?? 'none';
        } catch (\Throwable $e) {
            return $this->row($label, 'warn', 'Could not request a preview URL.');
        }

        if (in_array($status, self::CACHED_STATUSES, true)) {
            return $this->row($label, 'fail', 'A preview URL came back from the cache (cf-cache-status: ' . $status . '): the Cache Rule must leave out x-craft-preview, x-craft-live-preview and token=.');
        }

        return $this->row($label, 'ok', 'Preview URLs are not cached (cf-cache-status: ' . $status . ').');
    }

    /**
     * @return array{0: array, 1: ?string} the row, and `create`, `fix` or null (see check())
     */
    private function checkRule(string $host): array
    {
        $label = 'Cache Rule';
        $kvCache = Toolkit::getInstance()->kvCache;
        $zoneId = $kvCache->getCloudflareZoneId();
        $apiToken = $kvCache->getCloudflareApiToken();

        if ($zoneId === null || $apiToken === null) {
            return [$this->row($label, 'skip', 'Set the Zone ID and API token to check the rule on Cloudflare.'), null];
        }

        try {
            $ruleset = $this->getEntrypoint($zoneId, $apiToken);
        } catch (RequestException $e) {
            if (in_array($e->getResponse()?->getStatusCode(), [401, 403], true)) {
                return [$this->row($label, 'skip', 'The API token cannot read Cache Rules. Add the "Zone → Cache Rules → Edit" permission to it, or replace it with a token that has it, to check and create the rule from here.', [
                    'label' => 'Create token on Cloudflare',
                    'url' => $this->createTokenUrl(),
                ]), null];
            }

            return [$this->row($label, 'fail', $this->describeError($e)), null];
        } catch (\Throwable $e) {
            return [$this->row($label, 'fail', $this->describeError($e)), null];
        }

        $rules = $ruleset['rules'] ?? [];
        $rule = $this->findRule($rules, $host);

        if ($rule === null) {
            return [$this->row($label, 'fail', 'The zone has no Cache Rule for ' . $host . '.'), 'create'];
        }

        $problems = $this->ruleProblems($rule, $host);
        $name = '"' . ($rule['description'] ?? $rule['id']) . '"';

        if ($problems) {
            return [$this->row($label, 'fail', $name . ': ' . implode('; ', $problems) . '.'), 'fix'];
        }

        // every matching rule applies and the last one wins
        $index = array_search($rule, $rules, true);
        $later = array_filter(array_slice($rules, $index + 1), static fn(array $r) => ($r['enabled'] ?? true) && ($r['action'] ?? null) === 'set_cache_settings');

        if ($later) {
            return [$this->row($label, 'warn', $name . ' is set correctly, but ' . count($later) . ' rule(s) after it can override its settings.'), null];
        }

        return [$this->row($label, 'ok', $name . ' is set correctly.'), null];
    }

    private function ruleProblems(array $rule, string $host): array
    {
        $params = $rule['action_parameters'] ?? [];
        $expression = $rule['expression'] ?? '';
        $problems = [];

        if (!($rule['enabled'] ?? true)) {
            $problems[] = 'it is disabled';
        }

        if (($params['cache'] ?? null) !== true) {
            $problems[] = 'Cache eligibility must be "Eligible for cache"';
        }

        if (($params['edge_ttl']['mode'] ?? null) !== 'bypass_by_default') {
            $problems[] = 'Edge TTL must be "Use cache-control header if present, bypass cache if not"';
        }

        if (isset($params['browser_ttl']['mode']) && $params['browser_ttl']['mode'] !== 'respect_origin') {
            $problems[] = 'Browser TTL must be "Respect origin TTL"';
        }

        // _payload.json is fetched with ?_b=<build id>: dropping the query string serves an old build's payload
        if (!empty($params['cache_key']['custom_key']['query_string'])) {
            $problems[] = 'the cache key must keep the query string';
        }

        if (!str_contains($expression, '"' . $host . '"')) {
            $problems[] = 'the expression does not name ' . $host;
        }

        $missing = array_filter(
            array_merge(self::EXCLUDED_PATHS, self::EXCLUDED_QUERIES),
            static fn(string $needle) => !str_contains($expression, '"' . $needle . '"'),
        );

        if ($missing) {
            $problems[] = 'the expression does not leave out ' . implode(', ', $missing);
        }

        return $problems;
    }

    private function findRule(array $rules, string $host): ?array
    {
        foreach ($rules as $rule) {
            if (($rule['description'] ?? null) === self::RULE_DESCRIPTION) {
                return $rule;
            }
        }

        foreach ($rules as $rule) {
            if (($rule['action'] ?? null) === 'set_cache_settings' && str_contains($rule['expression'] ?? '', '"' . $host . '"')) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * The zone's Cache Rules ruleset, or null when the zone has none yet.
     */
    private function getEntrypoint(string $zoneId, string $apiToken): ?array
    {
        try {
            return $this->cloudflareRequest('GET', '/zones/' . rawurlencode($zoneId) . '/rulesets/phases/' . self::PHASE . '/entrypoint', $apiToken);
        } catch (RequestException $e) {
            if ($e->getResponse()?->getStatusCode() === 404) {
                return null;
            }

            throw $e;
        }
    }

    private function cloudflareRequest(string $method, string $path, string $apiToken, ?array $body = null): array
    {
        $client = new Client(['timeout' => 10, 'connect_timeout' => 5]);
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type' => 'application/json',
            ],
        ];

        if ($body !== null) {
            $options['body'] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $response = $client->request($method, self::CLOUDFLARE_API . $path, $options);
        $payload = json_decode((string)$response->getBody(), true);

        return is_array($payload['result'] ?? null) ? $payload['result'] : [];
    }

    /**
     * Requests a frontend page the way a visitor does, following redirects, and keeps the
     * headers that tell the frontend's and Cloudflare's caching apart.
     */
    private function probe(string $url): array
    {
        $effectiveUrl = $url;
        $client = new Client(['timeout' => 15, 'connect_timeout' => 5]);
        $response = $client->get($url, [
            'http_errors' => false,
            'headers' => [
                'Accept' => 'text/html',
                'User-Agent' => 'Craft Toolkit cache check',
            ],
            'on_stats' => static function(TransferStats $stats) use (&$effectiveUrl) {
                $effectiveUrl = (string)$stats->getEffectiveUri();
            },
        ]);

        $header = static fn(string $name) => $response->hasHeader($name) ? $response->getHeaderLine($name) : null;

        return [
            'url' => $effectiveUrl,
            'host' => (string)parse_url($effectiveUrl, PHP_URL_HOST),
            'status' => $response->getStatusCode(),
            'cdnCacheControl' => $header('CDN-Cache-Control'),
            'cacheStatus' => $header('cf-cache-status') !== null ? strtoupper($header('cf-cache-status')) : null,
            'cfRay' => $header('cf-ray'),
        ];
    }

    private function describeError(\Throwable $e): string
    {
        return Toolkit::getInstance()->kvCache->describeCloudflareError($e);
    }

    /**
     * @param array{label: string, url: string}|null $link a shortcut shown after the message
     */
    private function row(string $label, string $status, string $message, ?array $link = null): array
    {
        return ['label' => $label, 'status' => $status, 'message' => $message, 'link' => $link];
    }

    private function report(array $rows, ?string $ruleAction): array
    {
        $failed = count(array_filter($rows, static fn(array $row) => $row['status'] === 'fail'));

        return [
            'message' => $failed ? $failed . ' check(s) failed.' : 'All checks passed.',
            'checks' => $rows,
            'ruleAction' => $ruleAction,
        ];
    }
}
