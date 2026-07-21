<?php

namespace rondodevs\toolkit\graphql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class OrgSchemaType
{
    public const TYPE_NAME = 'ToolkitOrgSchema';

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
                    'siteHandle' => [
                        'name' => 'siteHandle',
                        'type' => Type::string(),
                    ],
                    'siteName' => [
                        'name' => 'siteName',
                        'type' => Type::string(),
                    ],
                    'type' => [
                        'name' => 'type',
                        'type' => Type::string(),
                        'description' => 'The schema.org @type for this identity (Organization, LocalBusiness, MedicalClinic, Person, ...).',
                    ],
                    'name' => [
                        'name' => 'name',
                        'type' => Type::string(),
                    ],
                    'legalName' => [
                        'name' => 'legalName',
                        'type' => Type::string(),
                    ],
                    'url' => [
                        'name' => 'url',
                        'type' => Type::string(),
                    ],
                    'logoUrl' => [
                        'name' => 'logoUrl',
                        'type' => Type::string(),
                    ],
                    'description' => [
                        'name' => 'description',
                        'type' => Type::string(),
                    ],
                    'email' => [
                        'name' => 'email',
                        'type' => Type::string(),
                    ],
                    'telephone' => [
                        'name' => 'telephone',
                        'type' => Type::string(),
                    ],
                    'sameAs' => [
                        'name' => 'sameAs',
                        'type' => Type::listOf(Type::string()),
                    ],
                    'addresses' => [
                        'name' => 'addresses',
                        'type' => Type::listOf(OrgSchemaAddressType::getType()),
                        'description' => 'One or more addresses/locations for this identity (e.g. multiple clinics for a multi-location business). Each address carries its own priceRange/openingHours, since those are place-specific.',
                    ],
                    'latestOrgSchemaUpdate' => [
                        'name' => 'latestOrgSchemaUpdate',
                        'type' => Type::string(),
                    ],
                ];
            },
        ]);

        GqlEntityRegistry::createEntity(self::TYPE_NAME, $type);

        return $type;
    }
}
