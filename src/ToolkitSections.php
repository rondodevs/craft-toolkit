<?php

namespace rondodevs\toolkit;

use craft\helpers\Cp;
use craft\helpers\UrlHelper;

/**
 * Central registry of the plugin's CP screens (id, label, route, icon,
 * description), used to build the home page cards, the per-page nav bar,
 * and the small icon shown next to each page's own heading.
 */
class ToolkitSections
{
    public static function all(): array
    {
        return [
            'site-config' => [
                'label' => 'Site Config',
                'route' => 'toolkit/site-config',
                'icon' => 'gear',
                'description' => 'CP-editable overrides for site name/URL on top of env defaults, exposed via GraphQL.',
            ],
            'kv-cache' => [
                'label' => 'KV Cache',
                'route' => 'toolkit/kv-cache',
                'icon' => 'database',
                'description' => 'Purge an external edge/KV cache (tags or full flush) when entries or assets change.',
            ],
            'static-labels' => [
                'label' => 'Static Labels',
                'route' => 'toolkit/static-labels',
                'icon' => 'tags',
                'description' => 'CP-editable label overrides per site, exposed via GraphQL.',
            ],
            'average-color' => [
                'label' => 'Average Color',
                'route' => 'toolkit/average-color',
                'icon' => 'palette',
                'description' => 'Enable and configure automatic average-color calculation on image assets, per volume.',
            ],
            'redirect' => [
                'label' => 'Redirect',
                'route' => 'toolkit/redirect',
                'icon' => 'route',
                'description' => 'Controls where the site root ("/") sends visitors in production.',
            ],
            'org-schema' => [
                'label' => 'Org Schema',
                'route' => 'toolkit/org-schema',
                'icon' => 'sitemap',
                'description' => 'schema.org / Nuxt SEO structured data defaults, plus a field type for per-entry schema pieces.',
            ],
        ];
    }

    public static function url(string $id): string
    {
        $route = self::all()[$id]['route'] ?? 'toolkit';
        return UrlHelper::cpUrl($route);
    }

    public static function iconSvg(string $id): ?string
    {
        $icon = self::all()[$id]['icon'] ?? null;
        return $icon ? Cp::iconSvg($icon) : null;
    }
}
