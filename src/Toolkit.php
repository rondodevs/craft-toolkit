<?php

namespace rondodevs\toolkit;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterCpAlertsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use rondodevs\toolkit\assets\ToolkitAsset;
use rondodevs\toolkit\handlers\CpHandlers;
use rondodevs\toolkit\handlers\GraphqlHandlers;
use rondodevs\toolkit\handlers\AssetHandlers;
use rondodevs\toolkit\handlers\KVCacheHandlers;
use rondodevs\toolkit\services\KvCacheService;
use rondodevs\toolkit\services\SiteConfigService;
use rondodevs\toolkit\services\StaticLabelsService;
use yii\base\Event;

/**
 * Toolkit plugin
 *
 * @method static Toolkit getInstance()
 */
class Toolkit extends Plugin
{
    public bool $hasCpSection = false;

    public function init(): void
    {
        parent::init();

        Craft::setAlias('@rondodevs/toolkit', __DIR__);

        $this->setComponents([
            'kvCache' => KvCacheService::class,
            'siteConfig' => SiteConfigService::class,
            'staticLabels' => StaticLabelsService::class,
        ]);

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function () {
            $this->attachEventHandlers();

            if (Craft::$app->getRequest()->getIsCpRequest()) {
                Craft::$app->getView()->registerAssetBundle(ToolkitAsset::class);
            }
        });
    }

    private function attachEventHandlers(): void
    {
        if (!Craft::$app->request->isConsoleRequest) {
            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_CP_URL_RULES,
                static function (RegisterUrlRulesEvent $event): void {
                    $event->rules['toolkit'] = 'toolkit/toolkit/index';
                    $event->rules['toolkit/site-config'] = 'toolkit/toolkit/site-config';
                    $event->rules['toolkit/kv-cache'] = 'toolkit/toolkit/kv-cache';
                    $event->rules['toolkit/static-labels'] = 'toolkit/toolkit/static-labels';
                }
            );

            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_SITE_URL_RULES,
                static function (RegisterUrlRulesEvent $event): void {
                    $event->rules[''] = 'toolkit/redirect/index';
                    // $event->rules['graphql'] = 'graphql/api';
                }
            );

            Event::on(
                Cp::class,
                Cp::EVENT_REGISTER_ALERTS,
                static function (RegisterCpAlertsEvent $event): void {
                    $routes = Craft::$app->getRoutes()->getConfigFileRoutes();

                    $graphqlRoute = $routes['graphql'] ?? null;
                    $hasDefaultGraphqlRoute =
                        $graphqlRoute === 'graphql/api' ||
                        (is_array($graphqlRoute) && ($graphqlRoute['route'] ?? null) === 'graphql/api');

                    if ($hasDefaultGraphqlRoute) {
                        return;
                    }

                    $event->alerts[] = Craft::t('app', 'Toolkit: Missing default GraphQL route in config/routes.php ({route}).', [
                        'route' => "'graphql' => 'graphql/api'",
                    ]) . ' <a class="go nowrap" href="' . UrlHelper::cpUrl('settings/routes') . '">' . Craft::t('app', 'Review routes') . '</a>';
                }
            );
        }

        // Register event handlers
        AssetHandlers::register();
        KVCacheHandlers::register();
        CpHandlers::init();
        GraphqlHandlers::register();
    }
}
