<?php

namespace rondodevs\toolkit\cp\screens;

use craft\helpers\App;
use craft\web\twig\variables\Cp;
use rondodevs\toolkit\services\CloudflareCacheRuleService;
use rondodevs\toolkit\Toolkit;
use rondodevs\toolkit\ToolkitSections;

final class CloudflareScreen implements Screen
{
    public static function props(): array
    {
        $toolkit = Toolkit::getInstance();
        $defaults = $toolkit->kvCache->getDefaultSettings();
        $overrides = $toolkit->kvCache->getOverrides();
        $resolved = $toolkit->kvCache->getResolvedSettings();
        $host = parse_url((string)$resolved['frontendUrl'], PHP_URL_HOST);

        return [
            'isDevMode' => App::devMode(),
            'settings' => [
                'cloudflareEnabled' => (bool)$resolved['cloudflareEnabled'],
                'cloudflareZoneId' => (string)$overrides['cloudflareZoneId'],
                'cloudflareApiToken' => (string)$overrides['cloudflareApiToken'],
            ],
            'defaults' => [
                'cloudflareZoneId' => $defaults['cloudflareZoneId'],
                'cloudflareApiToken' => $defaults['cloudflareApiToken'],
            ],
            'envVars' => self::envVars(),
            'createTokenUrl' => $toolkit->cloudflareCacheRule->createTokenUrl(),
            'kvCacheUrl' => ToolkitSections::url('kv-cache'),
            'advancedUrl' => ToolkitSections::url('advanced'),
            'cacheRuleName' => CloudflareCacheRuleService::RULE_DESCRIPTION,
            // The rule for the Frontend URL's host, to create it by hand. The host may still
            // redirect (apex → www): the check and the Create button use the host after redirects.
            'cacheRuleExpression' => $host ? $toolkit->cloudflareCacheRule->buildExpression($host) : null,
        ];
    }

    /**
     * The `$VARS` Craft would suggest in an autosuggest field.
     *
     * @return string[]
     */
    private static function envVars(): array
    {
        $names = [];

        foreach ((new Cp())->getEnvSuggestions() as $group) {
            foreach ($group['data'] ?? [] as $suggestion) {
                if (str_starts_with((string)($suggestion['name'] ?? ''), '$')) {
                    $names[] = $suggestion['name'];
                }
            }
        }

        return $names;
    }
}
