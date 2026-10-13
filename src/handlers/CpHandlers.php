<?php

namespace rondodevs\toolkit\handlers;

use Craft;
use craft\events\RegisterCpNavItemsEvent;
use craft\web\twig\variables\Cp;
use rondodevs\toolkit\ToolkitSections;
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
                    'subnav' => array_map(
                        static fn(array $section): array => [
                            'label' => $section['label'],
                            'url' => $section['route'],
                        ],
                        ToolkitSections::all()
                    ),
                ];
            }
        );
    }
}
