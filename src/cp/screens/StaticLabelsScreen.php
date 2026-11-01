<?php

namespace rondodevs\toolkit\cp\screens;

use rondodevs\toolkit\Toolkit;

final class StaticLabelsScreen implements Screen
{
    public static function props(): array
    {
        return [
            'sites' => array_map(static fn(array $site) => [
                'siteHandle' => $site['siteHandle'],
                'siteName' => $site['siteName'],
                'latestStaticLabelsUpdate' => $site['latestStaticLabelsUpdate'],
                'labels' => array_map(static fn(array $label) => [
                    'key' => $label['key'],
                    'mode' => $label['mode'],
                    'singleValue' => $label['singleValue'],
                    'zeroValue' => $label['zeroValue'],
                    'manyValue' => $label['manyValue'],
                ], $site['labels']),
            ], Toolkit::getInstance()->staticLabels->getResolvedStaticLabels()['sites']),
        ];
    }
}
