<?php

namespace rondodevs\toolkit\graphql\types\ether;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class EtherSeoKeywordType
{
    public const TYPE_NAME = 'Ether_SEOKeyword';

    public static function getType(): ObjectType
    {
        $type = GqlEntityRegistry::getEntity(self::TYPE_NAME);

        if ($type instanceof ObjectType) {
            return $type;
        }

        $type = new ObjectType([
            'name' => self::TYPE_NAME,
            'description' => 'Deprecated: Ether SEO compatibility, always empty.',
            'fields' => [
                'keyword' => Type::string(),
                'rating' => Type::string(),
            ],
        ]);

        GqlEntityRegistry::createEntity(self::TYPE_NAME, $type);

        return $type;
    }
}
