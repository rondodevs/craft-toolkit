<?php

namespace rondodevs\toolkit\handlers;

use craft\base\Element;
use craft\events\ModelEvent;
use craft\events\PluginEvent;
use craft\events\RegisterCacheOptionsEvent;
use craft\services\Plugins;
use craft\utilities\ClearCaches;
use rondodevs\toolkit\seo\EtherLegacy;
use rondodevs\toolkit\Toolkit;
use yii\base\Event;

/**
 * Automatic redirects when an element's URI changes, Ether SEO's redirects
 * kept when it's uninstalled, and the frontend's redirect cache in
 * Utilities → Caches.
 */
class RedirectsHandlers
{
    public static function register(): void
    {
        Event::on(
            Element::class,
            Element::EVENT_BEFORE_SAVE,
            static function (ModelEvent $event): void {
                /** @var Element $element */
                $element = $event->sender;

                if ($element::hasUris()) {
                    Toolkit::getInstance()->redirects->rememberUri($element);
                }
            }
        );

        Event::on(
            Element::class,
            Element::EVENT_AFTER_SAVE,
            static function (ModelEvent $event): void {
                /** @var Element $element */
                $element = $event->sender;

                if ($element::hasUris()) {
                    Toolkit::getInstance()->redirects->redirectChangedUri($element);
                }
            }
        );

        // Also when the project config uninstalls it, before its tables are dropped
        Event::on(
            Plugins::class,
            Plugins::EVENT_BEFORE_UNINSTALL_PLUGIN,
            static function (PluginEvent $event): void {
                if ($event->plugin->handle === EtherLegacy::PLUGIN_HANDLE) {
                    Toolkit::getInstance()->redirects->importFromEther();
                }
            }
        );

        Event::on(
            ClearCaches::class,
            ClearCaches::EVENT_REGISTER_CACHE_OPTIONS,
            static function (RegisterCacheOptionsEvent $event): void {
                $event->options[] = [
                    'key' => 'toolkit-redirects',
                    'label' => 'Frontend redirects',
                    'info' => 'The redirects the frontend has looked up and cached.',
                    'action' => static fn() => Toolkit::getInstance()->redirects->purgeFrontend(),
                ];
            }
        );
    }
}
