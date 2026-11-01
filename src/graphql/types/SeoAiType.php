<?php

namespace rondodevs\toolkit\graphql\types;

use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use rondodevs\toolkit\seo\SeoData;

class SeoAiType
{
    public const TYPE_NAME = 'ToolkitSeoAi';

    public static function getType(): ObjectType
    {
        $type = GqlEntityRegistry::getEntity(self::TYPE_NAME);

        if ($type instanceof ObjectType) {
            return $type;
        }

        $type = new ObjectType([
            'name' => self::TYPE_NAME,
            'description' => 'What AI assistants and answer engines get about an element, e.g. for llms.txt.',
            'fields' => static fn(): array => [
                'summary' => [
                    'type' => Type::string(),
                    'description' => 'A short, factual summary of the page for language models, falling back to the description.',
                    'resolve' => static fn(SeoData $seo): string => $seo->getAiSummary(),
                ],
                'exclude' => [
                    'type' => Type::nonNull(Type::boolean()),
                    'description' => 'Whether the page should be left out of llms.txt and AI training (`noai`/`noimageai` are in `robots` too).',
                    'resolve' => static fn(SeoData $seo): bool => $seo->aiExclude,
                ],
            ],
        ]);

        GqlEntityRegistry::createEntity(self::TYPE_NAME, $type);

        return $type;
    }
}
