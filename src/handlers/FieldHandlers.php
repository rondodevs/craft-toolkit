<?php

namespace rondodevs\toolkit\handlers;

use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use rondodevs\toolkit\fields\OrgSchemaField;
use yii\base\Event;

class FieldHandlers
{
    public static function register(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            static function (RegisterComponentTypesEvent $event): void {
                $event->types[] = OrgSchemaField::class;
            }
        );
    }
}
