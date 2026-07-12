<?php

namespace rondodevs\toolkit\graphql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class SiteConfigType
{
    public const TYPE_NAME = 'ToolkitSiteConfig';

    public static function getType(): ObjectType
    {
        $type = GqlEntityRegistry::getEntity(self::TYPE_NAME);

        if ($type instanceof ObjectType) {
            return $type;
        }

        $type = new ObjectType([
            'name' => self::TYPE_NAME,
            'fields' => static function (): array {
                return [
                    'siteUrl' => [
                        'name' => 'siteUrl',
                        'type' => Type::string(),
                    ],
                    'siteName' => [
                        'name' => 'siteName',
                        'type' => Type::string(),
                    ],
                    'latestSiteConfigUpdate' => [
                        'name' => 'latestSiteConfigUpdate',
                        'type' => Type::string(),
                    ],
                ];
            },
        ]);

        GqlEntityRegistry::createEntity(self::TYPE_NAME, $type);

        return $type;
    }
}
