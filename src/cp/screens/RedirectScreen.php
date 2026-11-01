<?php

namespace rondodevs\toolkit\cp\screens;

use craft\helpers\App;
use rondodevs\toolkit\Toolkit;

final class RedirectScreen implements Screen
{
    public static function props(): array
    {
        $service = Toolkit::getInstance()->redirect;
        $resolved = $service->getResolvedSettings();

        return [
            'isDevMode' => App::devMode(),
            'enabled' => (bool)$resolved['enabled'],
            'frontendUrl' => (string)$service->getOverrides()['frontendUrl'],
            'defaultFrontendUrl' => $service->getDefaultSettings()['frontendUrl'],
            'resolvedFrontendUrl' => (string)$resolved['frontendUrl'],
        ];
    }
}
