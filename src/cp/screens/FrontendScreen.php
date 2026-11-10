<?php

namespace rondodevs\toolkit\cp\screens;

use craft\helpers\App;
use rondodevs\toolkit\Toolkit;
use rondodevs\toolkit\ToolkitSections;

final class FrontendScreen implements Screen
{
    public static function props(): array
    {
        $service = Toolkit::getInstance()->frontendConfig;
        $defaults = $service->getDefaultSettings();
        $overrides = $service->getOverrides();
        $resolved = $service->getResolvedSettings();

        return [
            'isDevMode' => App::devMode(),
            'settings' => [
                'dataCache' => $resolved['dataCache'],
                'routeCache' => $resolved['routeCache'],
                'cdnCache' => $resolved['cdnCache'],
                'routeCacheMaxAge' => (string)($overrides['routeCacheMaxAge'] ?? ''),
                'routeCacheExclude' => (string)($overrides['routeCacheExclude'] ?? ''),
                'redirects' => $resolved['redirects'],
                'llms' => $resolved['llms'],
                'internalUrl' => (string)($overrides['internalUrl'] ?? ''),
            ],
            'defaults' => [
                'routeCacheMaxAge' => (string)$defaults['routeCacheMaxAge'],
                'internalUrl' => $defaults['internalUrl'],
            ],
            // what the frontend gets right now, after env vars and the container's hostname
            'resolvedInternalUrl' => $service->internalUrl(),
            'kvCacheUrl' => ToolkitSections::url('kv-cache'),
            'healthUrl' => ToolkitSections::url('health'),
        ];
    }
}
