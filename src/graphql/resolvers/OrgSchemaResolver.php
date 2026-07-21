<?php

namespace rondodevs\toolkit\graphql\resolvers;

use GraphQL\Type\Definition\ResolveInfo;
use rondodevs\toolkit\Toolkit;

class OrgSchemaResolver
{
    public static function resolve(mixed $source, array $arguments, mixed $context, ResolveInfo $resolveInfo): array
    {
        return Toolkit::getInstance()->orgSchema->getResolvedForSite((string)$arguments['site']);
    }
}
