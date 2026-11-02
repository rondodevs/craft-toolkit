<?php

namespace rondodevs\toolkit\cp\screens;

use Craft;
use craft\fields\MissingField;
use craft\helpers\UrlHelper;
use rondodevs\toolkit\fields\OrgSchemaField;
use rondodevs\toolkit\seo\EtherLegacy;
use rondodevs\toolkit\Toolkit;

final class SeoScreen implements Screen
{
    public static function props(): array
    {
        $seo = Toolkit::getInstance()->seo;

        return [
            'etherInstalled' => $seo->isEtherInstalled(),
            'etherInComposer' => $seo->isEtherInComposer(),
            'etherFields' => array_map(static fn($field) => [
                'name' => $field->name,
                'handle' => $field->handle,
                'missing' => $field instanceof MissingField,
            ], $seo->getEtherFields()),
            'etherRedirectCount' => $seo->getEtherRedirectCount(),
            'toolkitFields' => array_map(static fn($field) => [
                'name' => $field->name,
                'handle' => $field->handle,
                'cpEditUrl' => $field->getCpEditUrl(),
            ], $seo->getToolkitFields()),
            'usages' => array_map(static fn(array $usage) => [
                'entryType' => [
                    'name' => $usage['entryType']->name,
                    'handle' => $usage['entryType']->handle,
                    'cpEditUrl' => $usage['entryType']->getCpEditUrl(),
                ],
                'fieldHandle' => $usage['field']->handle,
                'rules' => array_keys($usage['rules']),
            ], $seo->getUsages()),
            // Left by the merge migration where the layout has no SEO field
            'orgSchemaFields' => array_values(array_map(static fn($field) => [
                'name' => $field->name,
                'handle' => $field->handle,
                'cpEditUrl' => $field->getCpEditUrl(),
            ], array_filter(Craft::$app->getFields()->getAllFields(), static fn($field) => $field instanceof OrgSchemaField))),
            'canMigrate' => $seo->canMigrate(),
            'compatEnabled' => EtherLegacy::isGraphqlCompatEnabled(),
            'deprecationsUrl' => UrlHelper::cpUrl('utilities/deprecation-errors'),
            'fieldsUrl' => UrlHelper::cpUrl('settings/fields'),
        ];
    }
}
