<?php

namespace rondodevs\toolkit\cp\screens;

use rondodevs\toolkit\ToolkitSections;

final class AdvancedScreen implements Screen
{
    public static function props(): array
    {
        return [
            'kvCacheUrl' => ToolkitSections::url('kv-cache'),
            'cloudflareUrl' => ToolkitSections::url('cloudflare'),
        ];
    }
}
