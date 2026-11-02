<?php

namespace rondodevs\toolkit\cp\screens;

use Craft;
use craft\helpers\UrlHelper;
use rondodevs\toolkit\redirects\RedirectMatcher;
use rondodevs\toolkit\Toolkit;

final class RedirectsScreen implements Screen
{
    public static function props(): array
    {
        $service = Toolkit::getInstance()->redirects;
        $sites = Craft::$app->getSites()->getAllSites();

        return [
            'redirects' => array_map(static fn(array $redirect) => $redirect + [
                'pattern' => RedirectMatcher::isRegex($redirect['sourceUri']),
            ], $service->all()),
            'sites' => array_map(static fn($site) => ['id' => $site->id, 'name' => $site->name, 'handle' => $site->handle], $sites),
            'auto' => $service->isAutoEnabled(),
            'canChangeSettings' => Craft::$app->getConfig()->getGeneral()->allowAdminChanges,
            'exportUrl' => UrlHelper::actionUrl('toolkit/redirects/export'),
        ];
    }
}
