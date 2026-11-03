<?php

namespace rondodevs\toolkit\cp\screens;

use rondodevs\toolkit\services\HealthService;
use rondodevs\toolkit\services\KvCacheService;

final class HealthScreen implements Screen
{
    public static function props(): array
    {
        return [
            'tokenEnv' => KvCacheService::AUTH_TOKEN_ENV,
            'frontendHealthPath' => HealthService::FRONTEND_HEALTH_PATH,
        ];
    }
}
