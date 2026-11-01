<?php

namespace rondodevs\toolkit\web;

use craft\helpers\Html;
use craft\helpers\Json;

/**
 * Where a Vue component of the CP app mounts (app/main.ts), with the props it starts from.
 */
final class Island
{
    public static function render(string $name, array $props, array $attributes = []): string
    {
        return Html::tag('div', '', $attributes + [
            'data' => [
                'tk-app' => $name,
                'props' => Json::encode($props),
            ],
        ]);
    }
}
