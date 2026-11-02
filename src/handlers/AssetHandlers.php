<?php

namespace rondodevs\toolkit\handlers;

use craft\elements\Asset;
use craft\events\ModelEvent;
use rondodevs\toolkit\Toolkit;
use yii\base\Event;

class AssetHandlers
{
    public static function register(): void
    {
        Event::on(
            Asset::class,
            Asset::EVENT_BEFORE_SAVE,
            static function (ModelEvent $event): void {
                /** @var Asset $asset */
                $asset = $event->sender;

                if ($asset->kind !== Asset::KIND_IMAGE) {
                    return;
                }

                $averageColor = Toolkit::getInstance()->averageColor;

                if (!$averageColor->appliesTo($asset)) {
                    return;
                }

                // Skip if not a new asset and file hasn't changed
                if (!empty($asset->getFieldValue('averageColor'))) {
                    return;
                }

                $color = $averageColor->calculate($asset);

                if ($color !== null) {
                    $asset->setFieldValue('averageColor', $color);
                }
            }
        );
    }
}
