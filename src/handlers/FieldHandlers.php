<?php

namespace rondodevs\toolkit\handlers;

use Craft;
use craft\base\Field;
use craft\events\DefineFieldHtmlEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\fields\Link;
use craft\fields\linktypes\Email;
use rondodevs\toolkit\fields\EmailLinkType;
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

        // On every request: the label of an email link is its address, its subject and body left out
        Event::on(
            Link::class,
            Link::EVENT_REGISTER_LINK_TYPES,
            static function (RegisterComponentTypesEvent $event): void {
                $event->types = array_map(fn(string $type) => $type === Email::class ? EmailLinkType::class : $type, $event->types);
            }
        );

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            // Subject and body for email links, in Craft's own URL suffix (`?subject=…&body=…`) when the field has
            // "URL Suffix", in the link's value otherwise (EmailLinkType): the link's `url` carries them everywhere, GraphQL included
            Event::on(
                Link::class,
                Field::EVENT_DEFINE_INPUT_HTML,
                static function (DefineFieldHtmlEvent $event): void {
                    /** @var Link $field */
                    $field = $event->sender;

                    if ($event->inline || !in_array('email', $field->types, true)) {
                        return;
                    }

                    $event->html .= Island::render('email-link', [
                        'inputId' => Craft::$app->getView()->namespaceInputId($field->getInputId()),
                        'types' => array_values($field->types),
                        'maxLength' => $field->maxLength,
                    ]);
                }
            );
        }
    }
}
