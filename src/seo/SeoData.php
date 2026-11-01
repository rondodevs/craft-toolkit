<?php

namespace rondodevs\toolkit\seo;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\web\View;
use rondodevs\toolkit\fields\SeoField;
use rondodevs\toolkit\Toolkit;
use Throwable;

/**
 * The value of a Toolkit SEO field: what the editor typed, plus the
 * resolution of everything left empty from the field's settings.
 *
 * Title: custom title, as typed → else the first non-empty title source →
 * title template → element title, wrapped in the prefix/suffix. Description: custom
 * description → first non-empty description source → description template.
 * Social title/description fall back to the resolved title/description, the
 * image to the image sources and then the field's default image, the AI
 * summary to the description.
 */
class SeoData
{
    public const ROBOTS_OPTIONS = ['noindex', 'nofollow', 'noarchive', 'nosnippet', 'noimageindex', 'notranslate'];

    /**
     * Directives added when an entry is excluded from AI: honoured by the
     * crawlers that support them, and the signal for llms.txt generators.
     */
    public const AI_ROBOTS = ['noai', 'noimageai'];

    public string $title = '';
    public string $description = '';
    public string $ogTitle = '';
    public string $ogDescription = '';
    public ?int $imageId = null;
    public array $robots = [];
    public string $canonical = '';
    public string $aiSummary = '';
    public bool $aiExclude = false;

    private array $resolved = [];

    /**
     * Sources and templates: the rules set on the field in the element's
     * field layout where there are, the field's otherwise (see SeoService::rulesFor()).
     */
    private readonly array $rules;

    public function __construct(
        private readonly SeoField $field,
        private readonly ?ElementInterface $element,
        array $values = [],
    ) {
        foreach (['title', 'description', 'ogTitle', 'ogDescription', 'canonical', 'aiSummary'] as $key) {
            $this->$key = trim((string)($values[$key] ?? ''));
        }

        $imageId = $values['imageId'] ?? null;
        if (is_array($imageId)) {
            $imageId = reset($imageId);
        }
        $this->imageId = is_numeric($imageId) && (int)$imageId > 0 ? (int)$imageId : null;

        $robots = is_array($values['robots'] ?? null) ? $values['robots'] : [];
        $this->robots = array_values(array_intersect(self::ROBOTS_OPTIONS, $robots));

        $this->aiExclude = !empty($values['aiExclude']);
        $this->rules = Toolkit::getInstance()->seo->rulesFor($field, $element);
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'ogTitle' => $this->ogTitle,
            'ogDescription' => $this->ogDescription,
            'imageId' => $this->imageId,
            'robots' => $this->robots,
            'canonical' => $this->canonical,
            'aiSummary' => $this->aiSummary,
            'aiExclude' => $this->aiExclude,
        ];
    }

    public function isEmpty(): bool
    {
        return $this->title === ''
            && $this->description === ''
            && $this->ogTitle === ''
            && $this->ogDescription === ''
            && $this->imageId === null
            && $this->robots === []
            && $this->canonical === ''
            && $this->aiSummary === ''
            && !$this->aiExclude;
    }

    public function getTitle(): string
    {
        // A custom title replaces the whole title: the prefix and suffix (e.g.
        // the site name) are only the automatic title's, so editors can drop them
        return $this->title !== '' ? $this->title : $this->getAutoTitle();
    }

    public function getTitlePrefix(): string
    {
        return $this->memo('titlePrefix', fn(): string => $this->render($this->field->titlePrefix));
    }

    public function getTitleSuffix(): string
    {
        return $this->memo('titleSuffix', fn(): string => $this->render($this->field->titleSuffix));
    }

    /**
     * The full title the field computes when no custom title is typed.
     */
    public function getAutoTitle(): string
    {
        return $this->memo('autoTitle', fn(): string => $this->getTitlePrefix() . $this->getAutoBaseTitle() . $this->getTitleSuffix());
    }

    /**
     * Where the automatic title comes from: "title", "field:<handle>" or "template".
     */
    public function getAutoTitleSource(): string
    {
        $this->getAutoBaseTitle();

        return $this->resolved['autoTitleSource'];
    }

    public function getDescription(): string
    {
        return $this->description !== '' ? $this->description : $this->getAutoDescription();
    }

    public function getAutoDescription(): string
    {
        return $this->memo('autoDescription', function(): string {
            $this->resolved['autoDescriptionSource'] = '';

            foreach ($this->rules['descriptionSources'] as $source) {
                $text = $this->sourceText($source);

                if ($text !== '') {
                    $this->resolved['autoDescriptionSource'] = $source;
                    return $this->truncate($text);
                }
            }

            $text = self::plainText($this->render($this->rules['descriptionTemplate']));

            if ($text !== '') {
                $this->resolved['autoDescriptionSource'] = 'template';
            }

            return $this->truncate($text);
        });
    }

    public function getAutoDescriptionSource(): string
    {
        $this->getAutoDescription();

        return $this->resolved['autoDescriptionSource'];
    }

    public function getOgTitle(): string
    {
        return $this->ogTitle !== '' ? $this->ogTitle : $this->getTitle();
    }

    public function getOgDescription(): string
    {
        return $this->ogDescription !== '' ? $this->ogDescription : $this->getDescription();
    }

    public function getCustomImage(): ?Asset
    {
        return $this->imageId ? $this->asset($this->imageId) : null;
    }

    public function getImage(): ?Asset
    {
        return $this->memo('image', fn(): ?Asset => $this->getCustomImage() ?? $this->getAutoImage());
    }

    public function getAutoImage(): ?Asset
    {
        return $this->memo('autoImage', function(): ?Asset {
            foreach ($this->rules['imageSources'] as $handle) {
                if ($image = $this->sourceImage($handle)) {
                    return $image;
                }
            }

            $defaultId = $this->field->defaultImageId();

            return $defaultId ? $this->asset($defaultId) : null;
        });
    }

    /**
     * The field's default directives, the entry's own and, when excluded
     * from AI, the AI ones.
     *
     * @return string[]
     */
    public function getRobots(): array
    {
        return array_values(array_unique(array_merge(
            $this->field->defaultRobots,
            $this->robots,
            $this->aiExclude ? self::AI_ROBOTS : [],
        )));
    }

    public function getRobotsContent(): ?string
    {
        $robots = $this->getRobots();

        return $robots === [] ? null : implode(', ', $robots);
    }

    public function getCanonical(): ?string
    {
        return $this->canonical !== '' ? $this->canonical : null;
    }

    public function getAiSummary(): string
    {
        return $this->aiSummary !== '' ? $this->aiSummary : $this->getDescription();
    }

    public function getElement(): ?ElementInterface
    {
        return $this->element;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    /**
     * The automatic title without the prefix and suffix.
     */
    public function getAutoBaseTitle(): string
    {
        return $this->memo('autoBaseTitle', function(): string {
            foreach ($this->rules['titleSources'] as $source) {
                $text = $this->sourceText($source);

                if ($text !== '') {
                    $this->resolved['autoTitleSource'] = $source;
                    return $text;
                }
            }

            $text = self::plainText($this->render($this->rules['titleTemplate']));

            if ($text !== '') {
                $this->resolved['autoTitleSource'] = 'template';
                return $text;
            }

            $this->resolved['autoTitleSource'] = 'title';

            return self::plainText($this->element->title ?? '');
        });
    }

    private function sourceText(string $source): string
    {
        if ($this->element === null) {
            return '';
        }

        if ($source === 'title') {
            return self::plainText($this->element->title ?? '');
        }

        if (!str_starts_with($source, 'field:')) {
            return '';
        }

        try {
            $value = $this->element->getFieldValue(substr($source, 6));
        } catch (Throwable) {
            // The field isn't in this element's layout
            return '';
        }

        return is_string($value) || $value instanceof \Stringable ? self::plainText((string)$value) : '';
    }

    private function sourceImage(string $handle): ?Asset
    {
        if ($this->element === null) {
            return null;
        }

        try {
            $value = $this->element->getFieldValue($handle);
        } catch (Throwable) {
            return null;
        }

        if ($value instanceof AssetQuery) {
            return (clone $value)->kind(Asset::KIND_IMAGE)->one();
        }

        if (is_iterable($value)) {
            foreach ($value as $asset) {
                if ($asset instanceof Asset && $asset->kind === Asset::KIND_IMAGE) {
                    return $asset;
                }
            }
        }

        return null;
    }

    private function asset(int $id): ?Asset
    {
        return Craft::$app->getAssets()->getAssetById($id, $this->element?->siteId);
    }

    /**
     * Renders an object template against the element, in site mode and on
     * the element's site, keeping the template's leading and trailing
     * whitespace (Craft trims it, which would glue a " - {{ siteName }}"
     * suffix to the title).
     */
    private function render(string $template): string
    {
        if (trim($template) === '') {
            return $template;
        }

        if ($this->element === null) {
            return '';
        }

        $view = Craft::$app->getView();
        $sites = Craft::$app->getSites();
        $oldSite = $sites->getCurrentSite();
        $oldMode = $view->getTemplateMode();

        try {
            $sites->setCurrentSite($this->element->getSite());
            $view->setTemplateMode(View::TEMPLATE_MODE_SITE);

            $rendered = $view->renderObjectTemplate($template, $this->element);
        } catch (Throwable $e) {
            Craft::warning("Toolkit SEO: unable to render \"$template\" - " . $e->getMessage(), __METHOD__);
            return '';
        } finally {
            $sites->setCurrentSite($oldSite);
            $view->setTemplateMode($oldMode);
        }

        preg_match('/^\s*/', $template, $leading);
        preg_match('/\s*$/', $template, $trailing);

        return $leading[0] . trim($rendered) . $trailing[0];
    }

    private function truncate(string $text): string
    {
        $max = $this->field->descriptionMaxLength;

        if ($max <= 0 || mb_strlen($text) <= $max) {
            return $text;
        }

        $cut = mb_substr($text, 0, $max - 1);
        $lastSpace = mb_strrpos($cut, ' ');

        if ($lastSpace !== false && $lastSpace > $max * 0.6) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " ,;:.-–") . '…';
    }

    /**
     * Rich text and Twig output reduced to one line of plain text, as meta
     * tags need it.
     */
    public static function plainText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $text = html_entity_decode(strip_tags(preg_replace('/<[^>]*>/', ' $0 ', $value)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private function memo(string $key, callable $resolve): mixed
    {
        if (!array_key_exists($key, $this->resolved)) {
            $this->resolved[$key] = $resolve();
        }

        return $this->resolved[$key];
    }
}
