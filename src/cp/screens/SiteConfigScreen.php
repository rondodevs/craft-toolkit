<?php

namespace rondodevs\toolkit\cp\screens;

use rondodevs\toolkit\Toolkit;

final class SiteConfigScreen implements Screen
{
    public static function props(): array
    {
        $service = Toolkit::getInstance()->siteConfig;
        $overrides = $service->getOverrides();

        return [
            'defaults' => $service->getDefaultSiteConfig(),
            'overrides' => [
                'siteName' => (string)$overrides['siteName'],
                'siteUrl' => (string)$overrides['siteUrl'],
            ],
            'resolved' => $service->getResolvedSiteConfig(),
        ];
    }
}
