<?php

namespace rondodevs\toolkit\graphql\types\ether;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;

class EtherSeoSocialNetworksType
{
    public const TYPE_NAME = 'Ether_SeoSocialNetworks';

    public static function getType(): ObjectType
    {
        $type = GqlEntityRegistry::getEntity(self::TYPE_NAME);

        if ($type instanceof ObjectType) {
            return $type;
        }

        $type = new ObjectType([
            'name' => self::TYPE_NAME,
            'description' => 'Deprecated: Ether SEO compatibility.',
            'fields' => static fn(): array => [
                'facebook' => [
                    'type' => EtherSeoSocialDataType::getType(),
                    'resolve' => static fn(array $networks) => $networks['facebook'],
                ],
                'twitter' => [
                    'type' => EtherSeoSocialDataType::getType(),
                    'resolve' => static fn(array $networks) => $networks['twitter'],
                ],
            ],
        ]);

        GqlEntityRegistry::createEntity(self::TYPE_NAME, $type);

        return $type;
    }
}
