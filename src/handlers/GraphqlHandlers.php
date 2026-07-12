<?php

namespace rondodevs\toolkit\handlers;


use craft\events\RegisterGqlQueriesEvent;
use craft\services\Gql;
use rondodevs\toolkit\graphql\resolvers\SiteConfigResolver;
use rondodevs\toolkit\graphql\resolvers\StaticLabelsResolver;
use rondodevs\toolkit\graphql\types\SiteConfigType;
use rondodevs\toolkit\graphql\types\StaticLabelsType;
use GraphQL\Type\Definition\Type;
use yii\base\Event;

class GraphqlHandlers
{
    public static function register(): void
    {

        Event::on(
            Gql::class,
            Gql::EVENT_REGISTER_GQL_QUERIES,
            static function (RegisterGqlQueriesEvent $event): void {
                $event->queries['siteConfig'] = [
                    'description' => 'Returns site metadata resolved from env defaults and Toolkit overrides.',
                    'type' => SiteConfigType::getType(),
                    'resolve' => [SiteConfigResolver::class, 'resolve'],
                ];

                $event->queries['staticLabels'] = [
                    'description' => 'Returns Toolkit static labels for the requested site handle.',
                    'type' => StaticLabelsType::getType(),
                    'args' => [
                        'site' => [
                            'name' => 'site',
                            'type' => Type::nonNull(Type::string()),
                        ],
                    ],
                    'resolve' => [StaticLabelsResolver::class, 'resolve'],
                ];
            }
        );
    }
}
