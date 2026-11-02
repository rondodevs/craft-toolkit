<?php

namespace rondodevs\toolkit;

use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use rondodevs\toolkit\cp\screens\AdvancedScreen;
use rondodevs\toolkit\cp\screens\AverageColorScreen;
use rondodevs\toolkit\cp\screens\CloudflareScreen;
use rondodevs\toolkit\cp\screens\KvCacheScreen;
use rondodevs\toolkit\cp\screens\OrgSchemaScreen;
use rondodevs\toolkit\cp\screens\RedirectScreen;
use rondodevs\toolkit\cp\screens\RedirectsScreen;
use rondodevs\toolkit\cp\screens\SeoScreen;
use rondodevs\toolkit\cp\screens\SiteConfigScreen;
use rondodevs\toolkit\cp\screens\StaticLabelsScreen;
use rondodevs\toolkit\cp\screens\StreamingVideoScreen;
use rondodevs\toolkit\cp\screens\UnusedAssetsScreen;

/**
 * The plugin's CP sections (id, label, route, screen, icon, description): the CP nav's subnav,
 * the nav bar on each screen and the icon next to its heading. Sections with a `group` share
 * one nav item, as tabs above their screens.
 */
class ToolkitSections
{
    /**
     * Nav items holding several sections as tabs, in the order of their first section.
     */
    public const GROUPS = [
        'assets' => ['label' => 'Assets', 'icon' => 'image'],
        'redirections' => ['label' => 'Redirects', 'icon' => 'signs-post'],
    ];

    public static function all(): array
    {
        return [
            'site-config' => [
                'label' => 'Site Config',
                'route' => 'toolkit/site-config',
                'screen' => SiteConfigScreen::class,
                'icon' => 'gear',
                'description' => 'CP-editable overrides for site name/URL on top of env defaults, exposed via GraphQL.',
            ],
            'kv-cache' => [
                'label' => 'KV Cache',
                'route' => 'toolkit/kv-cache',
                'screen' => KvCacheScreen::class,
                'icon' => 'database',
                'description' => 'Purge an external edge/KV cache (tags or full flush) when entries or assets change.',
            ],
            'cloudflare' => [
                'label' => 'Cloudflare',
                'route' => 'toolkit/cloudflare',
                'screen' => CloudflareScreen::class,
                'icon' => 'cloud',
                'description' => 'Purge the Cloudflare edge cache after every KV cache purge.',
            ],
            'static-labels' => [
                'label' => 'Static Labels',
                'route' => 'toolkit/static-labels',
                'screen' => StaticLabelsScreen::class,
                'icon' => 'tags',
                'description' => 'CP-editable label overrides per site, exposed via GraphQL.',
            ],
            'average-color' => [
                'group' => 'assets',
                'label' => 'Average Color',
                'route' => 'toolkit/average-color',
                'screen' => AverageColorScreen::class,
                'icon' => 'palette',
                'description' => 'Enable and configure automatic average-color calculation on image assets, per volume.',
            ],
            'redirects' => [
                'group' => 'redirections',
                'label' => 'Redirects',
                'route' => 'toolkit/redirects',
                'screen' => RedirectsScreen::class,
                'icon' => 'signs-post',
                'description' => 'URL redirects the frontend applies before rendering, automatic ones when an entry moves, imported from Ether SEO.',
            ],
            'redirect' => [
                'group' => 'redirections',
                'label' => 'Root redirect',
                'route' => 'toolkit/redirect',
                'screen' => RedirectScreen::class,
                'icon' => 'route',
                'description' => 'Controls where the site root ("/") sends visitors in production.',
            ],
            'org-schema' => [
                'label' => 'Org Schema',
                'route' => 'toolkit/org-schema',
                'screen' => OrgSchemaScreen::class,
                'icon' => 'sitemap',
                'description' => 'schema.org / Nuxt SEO structured data defaults, plus a field type for per-entry schema pieces.',
            ],
            'seo' => [
                'label' => 'SEO',
                'route' => 'toolkit/seo',
                'screen' => SeoScreen::class,
                'icon' => 'magnifying-glass',
                'description' => 'SEO field type with automatic titles, descriptions and images, robots, canonical and AI summary. Replaces Ether SEO.',
            ],
            'streaming-video' => [
                'group' => 'assets',
                'label' => 'Streaming Video',
                'route' => 'toolkit/streaming-video',
                'screen' => StreamingVideoScreen::class,
                'icon' => 'circle-play',
                'description' => 'Encodes video assets to HLS in the queue and exposes them via GraphQL. Needs CRAFT_STREAMING_VIDEO=true.',
            ],
            'unused-assets' => [
                'group' => 'assets',
                'label' => 'Unused Assets',
                'route' => 'toolkit/unused-assets',
                'screen' => UnusedAssetsScreen::class,
                'icon' => 'trash',
                'description' => 'Finds the assets of a volume that no content uses, and moves them to the trash.',
            ],
            'advanced' => [
                'label' => 'Advanced',
                'route' => 'toolkit/advanced',
                'screen' => AdvancedScreen::class,
                'icon' => 'broom',
                'description' => 'Emergency purge of every cache layer: Craft, the KV cache and Cloudflare.',
            ],
        ];
    }

    /**
     * The nav items: a section, or a group in place of its sections, linking to its first one.
     *
     * @return array<string, array{label: string, route: string, icon: string}>
     */
    public static function nav(): array
    {
        $items = [];

        foreach (self::all() as $id => $section) {
            $group = $section['group'] ?? null;

            if ($group === null) {
                $items[$id] = ['label' => $section['label'], 'route' => $section['route'], 'icon' => $section['icon']];
            } elseif (!isset($items[$group])) {
                $items[$group] = self::GROUPS[$group] + ['route' => $section['route']];
            }
        }

        return $items;
    }

    /**
     * The nav item a section is under: its group, or itself.
     */
    public static function navId(string $id): string
    {
        return self::all()[$id]['group'] ?? $id;
    }

    /**
     * The tabs of the section's group, none for a section on its own.
     *
     * @return array<int, array{id: string, label: string, url: string}>
     */
    public static function tabs(string $id): array
    {
        $group = self::all()[$id]['group'] ?? null;

        if ($group === null) {
            return [];
        }

        $tabs = [];

        foreach (self::all() as $sectionId => $section) {
            if (($section['group'] ?? null) === $group) {
                $tabs[] = ['id' => $sectionId, 'label' => $section['label'], 'url' => self::url($sectionId)];
            }
        }

        return $tabs;
    }

    public static function url(string $id): string
    {
        $route = self::all()[$id]['route'] ?? 'toolkit';
        return UrlHelper::cpUrl($route);
    }

    /**
     * The section's screen props, for the screen and after a save.
     */
    public static function props(string $id): array
    {
        return self::all()[$id]['screen']::props();
    }

    /**
     * The icon of a nav item: a section or a group.
     */
    public static function iconSvg(string $id): ?string
    {
        $icon = self::nav()[$id]['icon'] ?? self::all()[$id]['icon'] ?? null;
        return $icon ? Cp::iconSvg($icon) : null;
    }
}
