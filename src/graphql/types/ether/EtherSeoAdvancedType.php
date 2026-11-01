<?php

namespace rondodevs\toolkit\graphql\types\ether;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use rondodevs\toolkit\seo\SeoData;

class EtherSeoAdvancedType
{
    public const TYPE_NAME = 'Ether_SeoAdvanced';

    public static function getType(): ObjectType
    {
        $type = GqlEntityRegistry::getEntity(self::TYPE_NAME);

        if ($type instanceof ObjectType) {
            return $type;
        }

        $type = new ObjectType([
            'name' => self::TYPE_NAME,
            'description' => 'Deprecated: Ether SEO compatibility. Robots and canonical data',
            'fields' => static fn(): array => [
                'robots' => [
                    'type' => Type::listOf(Type::string()),
                    'resolve' => static fn(SeoData $seo): array => $seo->getRobots(),
                ],
                'canonical' => [
                    'type' => Type::string(),
                    'resolve' => static fn(SeoData $seo): ?string => $seo->getCanonical(),
                ],
            ],
        ]);

        GqlEntityRegistry::createEntity(self::TYPE_NAME, $type);

        return $type;
    }
}
