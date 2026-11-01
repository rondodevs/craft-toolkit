<?php

namespace rondodevs\toolkit\cp\screens;

use Craft;
use rondodevs\toolkit\Toolkit;

final class AverageColorScreen implements Screen
{
    public static function props(): array
    {
        $service = Toolkit::getInstance()->averageColor;

        return [
            'enabled' => (bool)$service->getResolvedSettings()['enabled'],
            'volumes' => $service->getVolumeStatuses(),
            'allowAdminChanges' => Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
        ];
    }
}
