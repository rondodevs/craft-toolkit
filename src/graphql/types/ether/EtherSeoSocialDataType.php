<?php

namespace rondodevs\toolkit\graphql\types\ether;

use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\elements\Asset as AssetInterface;
use craft\helpers\Gql;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use rondodevs\toolkit\seo\SeoData;

class EtherSeoSocialDataType
{
    public const TYPE_NAME = 'Ether_SeoSocialData';

    public static function getType(): ObjectType
    {
        $type = GqlEntityRegistry::getEntity(self::TYPE_NAME);

        if ($type instanceof ObjectType) {
            return $type;
        }

        $type = new ObjectType([
            'name' => self::TYPE_NAME,
            'description' => 'Deprecated: Ether SEO compatibility. Social data for an individual Social network',
            'fields' => static function(): array {
                $fields = [
                    'title' => [
                        'type' => Type::string(),
                        'resolve' => static fn(SeoData $seo): string => $seo->getOgTitle(),
                    ],
                    'description' => [
                        'type' => Type::string(),
                        'resolve' => static fn(SeoData $seo): string => $seo->getOgDescription(),
                    ],
                ];

                if (Gql::canQueryAssets()) {
                    $fields['image'] = [
                        'type' => AssetInterface::getType(),
                        'resolve' => static fn(SeoData $seo) => $seo->getImage(),
                    ];
                }

                return $fields;
            },
        ]);

        GqlEntityRegistry::createEntity(self::TYPE_NAME, $type);

        return $type;
    }
}
