<?php

namespace rondodevs\toolkit\handlers;

use Craft;
use craft\base\Element;
use craft\controllers\FieldsController;
use craft\events\DefineHtmlEvent;
use craft\events\TemplateEvent;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\ArrayHelper;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\web\View;
use rondodevs\toolkit\fields\SeoField;
use rondodevs\toolkit\Toolkit;
use rondodevs\toolkit\web\Island;
use Throwable;
use yii\base\ActionEvent;
use yii\base\Event;

/**
 * Puts the SEO rules in the SEO field's settings slideout in the field
 * layout designer, so each entry type can read its own fields, and the SEO
 * previews in the sidebar of the elements that have the field.
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

                // This event fires before FieldsController's own checks (login, CSRF, admin,
                // allowAdminChanges): the same ones here, or anyone could write the project
                // config. Otherwise the request goes on untouched and Craft rejects it
                if (!Craft::$app->getUser()->getIsAdmin()
                    || !Craft::$app->getConfig()->getGeneral()->allowAdminChanges
                    || !$request->validateCsrfToken()
                    || !StringHelper::isUUID($uid)
                ) {
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

        // Always in sight while editing, whatever tab the SEO field is on; the field's input feeds it
        Event::on(
            Element::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            static function (DefineHtmlEvent $event): void {
                /** @var Element $element */
                $element = $event->sender;

                // Revisions show the fields read-only, without the input that feeds the previews
                if ($event->static) {
                    return;
                }

                foreach ($element->getFieldLayout()?->getCustomFields() ?? [] as $field) {
                    if ($field instanceof SeoField) {
                        $event->html .= Html::tag('fieldset',
                            Html::tag('legend', 'SEO', ['class' => 'h6'])
                            . Html::tag('div', Island::render('seo-sidebar', ['previewKey' => $field->previewKey($element)]), [
                                // Looks like Craft's .meta boxes, as wide too, without .meta: its rules are for its own rows (a -24px margin on .field)
                                'style' => 'margin-inline: var(--neg-padding); padding: 12px; min-width: 0; border-radius: 5px; background: var(--gray-050); box-shadow: 0 0 0 1px var(--hairline-color), 0 2px 12px color-mix(in srgb, var(--hairline-color) 50%, transparent)',
                            ]),
                            // A fieldset is as wide as its content by default (min-inline-size: min-content)
                            ['style' => 'min-width: 0'],
                        );
                        return;
                    }
                }
            }
        );
    }
}
