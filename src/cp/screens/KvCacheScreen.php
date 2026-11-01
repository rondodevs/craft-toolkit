<?php

namespace rondodevs\toolkit\cp\screens;

use craft\helpers\App;
use rondodevs\toolkit\Toolkit;

final class KvCacheScreen implements Screen
{
    private const FIELDS = ['frontendUrl', 'authToken', 'authHeaderName', 'requestTimeout', 'connectTimeout'];

    public static function props(): array
    {
        $service = Toolkit::getInstance()->kvCache;
        $defaults = $service->getDefaultSettings();
        $overrides = $service->getOverrides();
        $settings = ['enabled' => (bool)$service->getResolvedSettings()['enabled']];

        foreach (self::FIELDS as $key) {
            $settings[$key] = (string)($overrides[$key] ?? '');
        }

        return [
            'isDevMode' => App::devMode(),
            'settings' => $settings,
            'defaults' => array_intersect_key($defaults, array_flip(self::FIELDS)),
        ];
    }
}
