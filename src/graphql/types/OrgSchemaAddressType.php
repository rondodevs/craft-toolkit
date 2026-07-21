<?php

namespace rondodevs\toolkit\graphql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class OrgSchemaAddressType
{
    public const TYPE_NAME = 'ToolkitOrgSchemaAddress';

    public static function getType(): ObjectType
    {
        $type = GqlEntityRegistry::getEntity(self::TYPE_NAME);

        if ($type instanceof ObjectType) {
            return $type;
        }

        $type = new ObjectType([
            'name' => self::TYPE_NAME,
            'fields' => static function(): array {
                return [
                    'streetAddress' => [
                        'name' => 'streetAddress',
                        'type' => Type::string(),
                    ],
                    'addressLocality' => [
                        'name' => 'addressLocality',
                        'type' => Type::string(),
                    ],
                    'addressRegion' => [
                        'name' => 'addressRegion',
                        'type' => Type::string(),
                    ],
                    'postalCode' => [
                        'name' => 'postalCode',
                        'type' => Type::string(),
                    ],
                    'addressCountry' => [
                        'name' => 'addressCountry',
                        'type' => Type::string(),
                    ],
                    'priceRange' => [
                        'name' => 'priceRange',
                        'type' => Type::string(),
                        'description' => 'e.g. "$$" or "€€-€€€". Only meaningful for LocalBusiness-like identity types.',
                    ],
                    'openingHours' => [
                        'name' => 'openingHours',
                        'type' => Type::listOf(Type::string()),
                        'description' => 'e.g. ["Mo-Fr 09:00-18:00", "Sa 10:00-14:00"] for this specific location. Only meaningful for LocalBusiness-like identity types.',
                    ],
                ];
            },
        ]);

        GqlEntityRegistry::createEntity(self::TYPE_NAME, $type);

        return $type;
    }
}
