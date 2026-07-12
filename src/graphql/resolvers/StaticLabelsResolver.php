<?php

namespace rondodevs\toolkit\graphql\resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use rondodevs\toolkit\Toolkit;

class StaticLabelsResolver
{
    public static function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): array
    {
        return Toolkit::getInstance()->staticLabels->getResolvedLabelsForSite((string)$arguments['site']);
    }
}