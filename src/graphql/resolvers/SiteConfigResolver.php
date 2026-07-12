<?php

namespace rondodevs\toolkit\graphql\resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use rondodevs\toolkit\Toolkit;

class SiteConfigResolver
{
    public static function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveConfig): array
    {
        return Toolkit::getInstance()->siteConfig->getResolvedSiteConfig();
    }
}
