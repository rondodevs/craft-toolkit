<?php

namespace rondodevs\toolkit\graphql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class OrgSchemaFieldPieceType
{
    public const TYPE_NAME = 'ToolkitOrgSchemaFieldPiece';

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
                    'type' => [
                        'name' => 'type',
                        'type' => Type::string(),
                        'description' => 'The schema.org @type for this piece (e.g. "Article", "Product").',
                    ],
                    'propertiesJson' => [
                        'name' => 'propertiesJson',
                        'type' => Type::string(),
                        'description' => 'The additional schema.org properties for this piece, as a raw JSON object string (without @type).',
                    ],
                    'json' => [
                        'name' => 'json',
                        'type' => Type::string(),
                        'description' => 'The fully resolved schema.org piece (type + properties merged, including @type), as a JSON string ready to be parsed and passed to a schema.org renderer such as Nuxt SEO\'s useSchemaOrg().',
                    ],
                ];
            },
        ]);

        GqlEntityRegistry::createEntity(self::TYPE_NAME, $type);

        return $type;
    }
}
