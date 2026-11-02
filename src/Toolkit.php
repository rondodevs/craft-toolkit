<?php

namespace rondodevs\toolkit;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterCpAlertsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\helpers\Cp;
use craft\helpers\UrlHelper;
use craft\web\UrlManager;
use rondodevs\toolkit\handlers\CpHandlers;
use rondodevs\toolkit\handlers\FieldHandlers;
use rondodevs\toolkit\handlers\GraphqlHandlers;
use rondodevs\toolkit\handlers\AssetHandlers;
use rondodevs\toolkit\handlers\KVCacheHandlers;
use rondodevs\toolkit\handlers\SeoHandlers;
use rondodevs\toolkit\handlers\StreamingVideoHandlers;
use rondodevs\toolkit\seo\EtherLegacy;
use rondodevs\toolkit\services\AverageColorService;
use rondodevs\toolkit\services\CloudflareCacheRuleService;
use rondodevs\toolkit\services\KvCacheService;
use rondodevs\toolkit\services\OrgSchemaService;
use rondodevs\toolkit\services\PurgeService;
use rondodevs\toolkit\services\RedirectService;
use rondodevs\toolkit\services\SeoService;
use rondodevs\toolkit\services\SiteConfigService;
use rondodevs\toolkit\services\StaticLabelsService;
use rondodevs\toolkit\services\StreamingVideoService;
use rondodevs\toolkit\services\UnusedAssetsService;
use rondodevs\toolkit\web\ToolkitAsset;
use yii\base\Event;

/**
 * Toolkit plugin
 *
 * @method static Toolkit getInstance()
 */
class Toolkit extends Plugin
{
    public string $schemaVersion = '1.1.0';
    public bool $hasCpSection = false;
    public bool $hasCpSettings = true;

    public function getSettingsResponse(): mixed
    {
        return Craft::$app->getResponse()->redirect(UrlHelper::cpUrl('toolkit'));
    }

    public function init(): void
    {
        parent::init();

        Craft::setAlias('@rondodevs/toolkit', __DIR__);

        $this->setComponents([
            'averageColor' => AverageColorService::class,
            'cloudflareCacheRule' => CloudflareCacheRuleService::class,
            'kvCache' => KvCacheService::class,
            'orgSchema' => OrgSchemaService::class,
            'purge' => PurgeService::class,
            'redirect' => RedirectService::class,
            'seo' => SeoService::class,
            'siteConfig' => SiteConfigService::class,
            'staticLabels' => StaticLabelsService::class,
            'streamingVideo' => StreamingVideoService::class,
            'unusedAssets' => UnusedAssetsService::class,
        ]);

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function () {
            $this->attachEventHandlers();

            if (Craft::$app->getRequest()->getIsCpRequest()) {
                $this->registerCpApp();
            }
        });
    }

    /**
     * The CP app is built by CI into each release tag: a checkout of `main` has it only after
     * `pnpm build`. Without it the CP still works, the Toolkit screens stay empty.
     */
    private function registerCpApp(): void
    {
        if (!is_file(__DIR__ . '/web/dist/toolkit.js')) {
            Craft::warning('Toolkit: the CP app is not built (src/web/dist is missing): run `pnpm install && pnpm build` in the plugin.', __METHOD__);

            return;
        }

        Craft::$app->getView()->registerAssetBundle(ToolkitAsset::class);
    }

    private function attachEventHandlers(): void
    {
        if (!Craft::$app->request->isConsoleRequest) {
            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_CP_URL_RULES,
                static function (RegisterUrlRulesEvent $event): void {
                    $event->rules['toolkit'] = 'toolkit/toolkit/index';
                    $event->rules['toolkit/<section:[a-z-]+>'] = 'toolkit/toolkit/screen';
                }
            );

            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_SITE_URL_RULES,
                static function (RegisterUrlRulesEvent $event): void {
                    $event->rules[''] = 'toolkit/site-redirect/index';
                    $event->rules['graphql'] = 'graphql/api';
                }
            );

            Event::on(
                Cp::class,
                Cp::EVENT_REGISTER_ALERTS,
                static function (RegisterCpAlertsEvent $event): void {
                    $kvCache = Toolkit::getInstance()->kvCache;

                    if ($kvCache->isEnabled() && $kvCache->isEndpointUnreachable()) {
                        $event->alerts[] = Craft::t('app', 'Toolkit: the Nuxt frontend cache endpoint is unreachable. Cache purges are being skipped until it comes back online.') .
                            ' <a class="go nowrap" href="' . UrlHelper::cpUrl('toolkit/kv-cache') . '">' . Craft::t('app', 'Review settings') . '</a>';
                    }

                    if (Craft::$app->getUser()->getIsAdmin()) {
                        $seo = Toolkit::getInstance()->seo;

                        if (EtherLegacy::isPluginEnabled() || $seo->getEtherFields() !== []) {
                            $event->alerts[] = Craft::t('app', 'Toolkit now has its own SEO field: Ether SEO can be uninstalled. The existing GraphQL queries keep working, but they are deprecated and will be removed soon.') .
                                ' <a class="go nowrap" href="' . UrlHelper::cpUrl('toolkit/seo') . '">' . Craft::t('app', 'Migrate') . '</a>';
                        } elseif ($seo->isEtherInComposer() && !$seo->isEtherInstalled()) {
                            $event->alerts[] = Craft::t('app', 'Ether SEO is uninstalled: remove ether/seo from composer.json.');
                        }
                    }
                }
            );
        }

        // Register event handlers
        AssetHandlers::register();
        KVCacheHandlers::register();
        CpHandlers::init();
        GraphqlHandlers::register();
        FieldHandlers::register();
        SeoHandlers::register();
        StreamingVideoHandlers::register();
    }
}
