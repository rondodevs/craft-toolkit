<?php

namespace rondodevs\toolkit\support;

use Craft;
use craft\models\Site;

final class Sites
{
    /**
     * Enabled sites, in the CP's order, by handle.
     *
     * @return array<string, Site>
     */
    public static function enabled(): array
    {
        $sites = array_filter(Craft::$app->getSites()->getAllSites(), static fn(Site $site) => $site->getEnabled());
        usort($sites, static fn(Site $a, Site $b) => ($a->sortOrder ?? 0) <=> ($b->sortOrder ?? 0));

        $byHandle = [];

        foreach ($sites as $site) {
            $byHandle[$site->handle] = $site;
        }

        return $byHandle;
    }

    public static function requireEnabled(string $handle, string $what): Site
    {
        return self::enabled()[trim($handle)]
            ?? throw new \InvalidArgumentException("A valid site handle is required for $what.");
    }
}
