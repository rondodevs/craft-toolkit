<?php

namespace rondodevs\toolkit\handlers;

use Craft;
use craft\controllers\FieldsController;
use craft\events\TemplateEvent;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\ArrayHelper;
use craft\web\View;
use rondodevs\toolkit\fields\SeoField;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\base\ActionEvent;
use yii\base\Event;

/**
 * Puts the SEO rules in the SEO field's settings slideout in the field
 * layout designer, so each entry type can read its own fields.
 */
class SeoHandlers
{
    public const POST_PARAM = 'toolkitSeo';

    private const SETTINGS_TEMPLATE = '_includes/forms/fld/custom-field-settings.twig';

    public static function register(): void
    {
        // Rendered inside Craft's namespaceInputs(), so the inputs come back namespaced like the rest of the slideout
        Event::on(
            View::class,
            View::EVENT_AFTER_RENDER_TEMPLATE,
            static function (TemplateEvent $event): void {
                if ($event->template !== self::SETTINGS_TEMPLATE) {
                    return;
                }

                $element = $event->variables['field'] ?? null;

                if (!$element instanceof CustomField) {
                    return;
                }

                try {
                    $field = $element->getField();
                } catch (Throwable) {
                    return;
                }

                if (!$field instanceof SeoField) {
                    return;
                }

                $seo = Toolkit::getInstance()->seo;

                $event->output .= Craft::$app->getView()->renderTemplate('toolkit/fields/seo/layout-rules', [
                    'rules' => $seo->getRulesForLayoutElement($element->uid),
                    'options' => $seo->sourceOptionsForLayout($element->getLayout()),
                    'defaults' => [
                        'titleTemplate' => $field->titleTemplate,
                        'descriptionTemplate' => $field->descriptionTemplate,
                    ],
                ], View::TEMPLATE_MODE_CP);
            }
        );

        // Saves the rules on "Apply" and takes them out of the request: Craft
        // merges the posted settings into the layout element config, where an
        // unknown key would be an error
        Event::on(
            FieldsController::class,
            FieldsController::EVENT_BEFORE_ACTION,
            static function (ActionEvent $event): void {
                if ($event->action->id !== 'apply-layout-element-settings') {
                    return;
                }

                $request = Craft::$app->getRequest();
                $settingsStr = $request->getBodyParam('settings');
                $namespace = $request->getBodyParam('settingsNamespace');
                $uid = $request->getBodyParam('uid');

                if (!is_string($settingsStr) || !is_string($namespace) || !is_string($uid)) {
                    return;
                }

                parse_str($settingsStr, $posted);
                $settings = ArrayHelper::getValue($posted, $namespace);

                if (!is_array($settings) || !is_array($settings[self::POST_PARAM] ?? null)) {
                    return;
                }

                Toolkit::getInstance()->seo->saveRulesForLayoutElement($uid, $settings[self::POST_PARAM]);

                unset($settings[self::POST_PARAM]);
                ArrayHelper::setValue($posted, $namespace, $settings);

                $request->setBodyParams(['settings' => http_build_query($posted)] + $request->getBodyParams());
            }
        );
    }
}
