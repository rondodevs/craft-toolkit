<?php

namespace rondodevs\toolkit\graphql\types\ether;

use Craft;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use rondodevs\toolkit\graphql\types\SeoType;
use rondodevs\toolkit\seo\SeoData;

/**
 * Ether SEO's `Ether_SeoData`, kept as an interface that ToolkitSeo
 * implements: queries written for Ether keep working on fields converted to
 * the Toolkit SEO type, without touching the frontend. Deprecated: query
 * ToolkitSeo's own fields instead.
 */
class EtherSeoDataInterface
{
    public const TYPE_NAME = 'Ether_SeoData';
    public const DEPRECATION = 'Ether SEO compatibility, will be removed. Query the ToolkitSeo fields instead (ogTitle, ogDescription, image, robots, canonical).';

    public static function getType(): InterfaceType
    {
        $type = GqlEntityRegistry::getEntity(self::TYPE_NAME);

        if ($type instanceof InterfaceType) {
            return $type;
        }

        $type = new InterfaceType([
            'name' => self::TYPE_NAME,
            'description' => 'Deprecated: Ether SEO compatibility. Use the ToolkitSeo type.',
            'fields' => static fn(): array => [
                'title' => Type::string(),
                'description' => Type::string(),
            ] + self::legacyFields(),
            'resolveType' => static fn(): ObjectType => SeoType::getType(),
        ]);

        GqlEntityRegistry::createEntity(self::TYPE_NAME, $type);

        return $type;
    }

    /**
     * The Ether fields ToolkitSeo has no equivalent for under the same name.
     * Facebook and Twitter return the same social values, keywords are
     * always empty.
     */
    public static function legacyFields(): array
    {
        return [
            'keywords' => [
                'type' => Type::listOf(EtherSeoKeywordType::getType()),
                'deprecationReason' => self::DEPRECATION,
                'resolve' => static function(): array {
                    self::logUsage();
                    return [];
                },
            ],
            'social' => [
                'type' => EtherSeoSocialNetworksType::getType(),
                'deprecationReason' => self::DEPRECATION,
                'resolve' => static function(SeoData $seo): array {
                    self::logUsage();
                    return ['facebook' => $seo, 'twitter' => $seo];
                },
            ],
            'advanced' => [
                'type' => EtherSeoAdvancedType::getType(),
                'deprecationReason' => self::DEPRECATION,
                'resolve' => static function(SeoData $seo): SeoData {
                    self::logUsage();
                    return $seo;
                },
            ],
        ];
    }

    /**
     * Lists the frontends still on the Ether queries in Utilities →
     * Deprecation Warnings.
     */
    private static function logUsage(): void
    {
        Craft::$app->getDeprecator()->log(
            'toolkit.seo.etherGraphql',
            'The Ether SEO GraphQL fields (`Ether_SeoData`: `social`, `advanced`, `keywords`) are deprecated and will be removed from Toolkit. Query the `ToolkitSeo` fields instead: `title`, `description`, `ogTitle`, `ogDescription`, `image`, `robots`, `canonical`, `ai`.'
        );
    }
}
