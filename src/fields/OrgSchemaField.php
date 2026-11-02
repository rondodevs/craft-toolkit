<?php

namespace rondodevs\toolkit\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\helpers\Html;
use rondodevs\toolkit\graphql\types\OrgSchemaFieldPieceType;
use rondodevs\toolkit\seo\SchemaPieces;
use rondodevs\toolkit\web\Island;
use GraphQL\Type\Definition\Type;
use yii\db\Schema;

/**
 * Deprecated: the SEO field has the same structured data pieces now. Kept so
 * projects still using it keep working until they merge it into their SEO
 * field (Toolkit → SEO, or `php craft toolkit/seo/merge-org-schema`).
 */
class OrgSchemaField extends Field
{
    public static function displayName(): string
    {
        return 'Org Schema (deprecated)';
    }

    public static function icon(): string
    {
        return 'brackets-curly';
    }

    public static function phpType(): string
    {
        return 'array|null';
    }

    public static function dbType(): array|string|null
    {
        return Schema::TYPE_TEXT;
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? SchemaPieces::build($value) : [];
    }

    public function normalizeValueFromRequest(mixed $value, ?ElementInterface $element): mixed
    {
        $rows = is_array($value) && is_array($value['rows'] ?? null) ? $value['rows'] : [];

        return SchemaPieces::build($rows);
    }

    protected function inputHtml(mixed $value, ?ElementInterface $element, bool $inline): string
    {
        $name = Craft::$app->getView()->namespaceInputName($this->handle);

        return Html::tag('p', 'Deprecated: merge this field into the SEO field from Toolkit → SEO.', ['class' => 'warning'])
            . Island::render('org-schema-field', SchemaPieces::inputProps($name, is_array($value) ? $value : []));
    }

    public function getElementValidationRules(): array
    {
        return [
            [
                function(ElementInterface $element) {
                    $value = $element->getFieldValue($this->handle);
                    $error = is_array($value) ? SchemaPieces::error($value) : null;

                    if ($error !== null) {
                        $element->addError("field:$this->handle", $this->getUiLabel() . ' ' . $error);
                    }
                },
            ],
        ];
    }

    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        return !is_array($value) || $value === [];
    }

    public function serializeValue(mixed $value, ?ElementInterface $element): mixed
    {
        $stored = is_array($value) ? SchemaPieces::serialize($value) : [];

        return $stored === [] ? null : json_encode($stored, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function getContentGqlType(): Type|array
    {
        return Type::listOf(OrgSchemaFieldPieceType::getType());
    }

    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        if (!is_array($value) || $value === []) {
            return '';
        }

        $count = count($value);

        return Html::tag('code', $count . ' schema.org ' . ($count === 1 ? 'entry' : 'entries'));
    }
}
