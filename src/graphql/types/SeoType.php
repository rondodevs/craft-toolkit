<?php

namespace rondodevs\toolkit\graphql\types;

use craft\gql\GqlEntityRegistry;
use craft\gql\interfaces\elements\Asset as AssetInterface;
use craft\helpers\Gql;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use rondodevs\toolkit\graphql\types\ether\EtherSeoDataInterface;
use rondodevs\toolkit\seo\EtherLegacy;
use rondodevs\toolkit\seo\SeoData;

/**
 * The value of a Toolkit SEO field, every value already resolved.
 *
 * While Ether SEO isn't installed it also implements the Ether_SeoData
 * interface, so queries written for Ether (`fragment SEO on Ether_SeoData`)
 * keep validating and get the same values, through deprecated fields.
 */
class SeoType
{
    public const TYPE_NAME = 'ToolkitSeo';

    public static function getType(): ObjectType
    {
        $type = GqlEntityRegistry::getEntity(self::TYPE_NAME);

        if ($type instanceof ObjectType) {
            return $type;
        }

        $compat = EtherLegacy::isGraphqlCompatEnabled();

        $type = new ObjectType([
            'name' => self::TYPE_NAME,
            'description' => 'SEO values of an element, with automatic values already applied to whatever the editor left empty.',
            'interfaces' => $compat ? static fn(): array => [EtherSeoDataInterface::getType()] : [],
            'fields' => static function() use ($compat): array {
                $fields = [
                    'title' => [
                        'type' => Type::string(),
                        'description' => 'The page title: the custom title as typed, else the first non-empty title source, the title template or the element title, with the prefix and suffix.',
                        'resolve' => static fn(SeoData $seo): string => $seo->getTitle(),
                    ],
                    'description' => [
                        'type' => Type::string(),
                        'description' => 'The meta description: custom description, else the first non-empty description source, else the description template. Plain text.',
                        'resolve' => static fn(SeoData $seo): string => $seo->getDescription(),
                    ],
                    'ogTitle' => [
                        'type' => Type::string(),
                        'description' => 'The title for social sharing (og:title, twitter:title), falling back to `title`.',
                        'resolve' => static fn(SeoData $seo): string => $seo->getOgTitle(),
                    ],
                    'ogDescription' => [
                        'type' => Type::string(),
                        'description' => 'The description for social sharing (og:description, twitter:description), falling back to `description`.',
                        'resolve' => static fn(SeoData $seo): string => $seo->getOgDescription(),
                    ],
                    'robots' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
                        'description' => 'Robots directives: the field defaults, the entry\'s own, and `noai`/`noimageai` when excluded from AI.',
                        'resolve' => static fn(SeoData $seo): array => $seo->getRobots(),
                    ],
                    'robotsContent' => [
                        'type' => Type::string(),
                        'description' => 'The robots directives joined for a `<meta name="robots">` tag, or null when there are none.',
                        'resolve' => static fn(SeoData $seo): ?string => $seo->getRobotsContent(),
                    ],
                    'noindex' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => static fn(SeoData $seo): bool => in_array('noindex', $seo->getRobots(), true),
                    ],
                    'nofollow' => [
                        'type' => Type::nonNull(Type::boolean()),
                        'resolve' => static fn(SeoData $seo): bool => in_array('nofollow', $seo->getRobots(), true),
                    ],
                    'canonical' => [
                        'type' => Type::string(),
                        'description' => 'The canonical URL typed by the editor, or null to use the page\'s own URL.',
                        'resolve' => static fn(SeoData $seo): ?string => $seo->getCanonical(),
                    ],
                    'schema' => [
                        'type' => Type::nonNull(Type::listOf(Type::nonNull(OrgSchemaFieldPieceType::getType()))),
                        'description' => 'The entry\'s schema.org pieces: `json` is each one ready to parse and pass to Nuxt SEO\'s useSchemaOrg().',
                        'resolve' => static fn(SeoData $seo): array => $seo->schema,
                    ],
                    'ai' => [
                        'type' => Type::nonNull(SeoAiType::getType()),
                        'resolve' => static fn(SeoData $seo): SeoData => $seo,
                    ],
                ];

                if (Gql::canQueryAssets()) {
                    $fields['image'] = [
                        'type' => AssetInterface::getType(),
                        'description' => 'The image for social sharing: custom image, else the first image of the image sources, else the field\'s default image.',
                        'resolve' => static fn(SeoData $seo) => $seo->getImage(),
                    ];
                }

                if ($compat) {
                    $fields += EtherSeoDataInterface::legacyFields();
                }

                return $fields;
            },
        ]);

        GqlEntityRegistry::createEntity(self::TYPE_NAME, $type);

        return $type;
    }
}
