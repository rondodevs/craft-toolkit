<?php

namespace rondodevs\toolkit\handlers;

use Craft;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use yii\base\Event;

class CpHandlers
{
    public static function init(): void
    {
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS,
            static function (RegisterCpNavItemsEvent $event): void {
                if (!Craft::$app->getUser()->getIsAdmin()) {
                    return;
                }

                $event->navItems[] = [
                    'label' => 'Toolkit',
                    'url' => 'toolkit',
                    'subnav' => [
                        'site-config' => [
                            'label' => 'Site Config',
                            'url' => 'toolkit/site-config',
                        ],
                        'kv-cache' => [
                            'label' => 'KV Cache',
                            'url' => 'toolkit/kv-cache',
                        ],
                        'static-labels' => [
                            'label' => 'Static Labels',
                            'url' => 'toolkit/static-labels',
                        ],
                        'average-color' => [
                            'label' => 'Average Color',
                            'url' => 'toolkit/average-color',
                        ],
                    ],
                ];
            }
        );
    }
}
