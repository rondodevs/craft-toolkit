<?php

namespace rondodevs\toolkit\handlers;

use Craft;
use craft\base\Field;
use craft\events\DefineFieldHtmlEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\fields\Link;
use craft\services\Fields;
use rondodevs\toolkit\fields\OrgSchemaField;
use rondodevs\toolkit\fields\SeoField;
use rondodevs\toolkit\web\Island;
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
                $event->types[] = SeoField::class;
            }
        );

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            // Subject and body for email links, written into Craft's own URL suffix (`?subject=…&body=…`), so the
            // link's `url` carries them everywhere, GraphQL included. Only for Link fields with the "URL Suffix" advanced field
            Event::on(
                Link::class,
                Field::EVENT_DEFINE_INPUT_HTML,
                static function (DefineFieldHtmlEvent $event): void {
                    /** @var Link $field */
                    $field = $event->sender;

                    if ($event->inline || !in_array('email', $field->types, true) || !in_array('urlSuffix', $field->advancedFields, true)) {
                        return;
                    }

                    $event->html .= Island::render('email-link', [
                        'inputId' => Craft::$app->getView()->namespaceInputId($field->getInputId()),
                        'types' => array_values($field->types),
                    ]);
                }
            );
        }
    }
}
