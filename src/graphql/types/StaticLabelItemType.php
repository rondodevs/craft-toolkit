<?php

namespace rondodevs\toolkit\graphql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

class StaticLabelItemType
{
    public const TYPE_NAME = 'ToolkitStaticLabelItem';

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
                    'key' => [
                        'name' => 'key',
                        'type' => Type::string(),
                    ],
                    'mode' => [
                        'name' => 'mode',
                        'type' => Type::string(),
                    ],
                    'value' => [
                        'name' => 'value',
                        'type' => Type::string(),
                    ],
                    'singleValue' => [
                        'name' => 'singleValue',
                        'type' => Type::string(),
                    ],
                    'zeroValue' => [
                        'name' => 'zeroValue',
                        'type' => Type::string(),
                    ],
                    'manyValue' => [
                        'name' => 'manyValue',
                        'type' => Type::string(),
                    ],
                ];
            },
        ]);

        GqlEntityRegistry::createEntity(self::TYPE_NAME, $type);

        return $type;
    }
}