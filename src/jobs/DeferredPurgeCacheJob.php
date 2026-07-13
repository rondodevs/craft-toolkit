<?php

namespace rondodevs\toolkit\jobs;

use Craft;
use craft\elements\Entry;
use craft\elements\Asset;
use craft\queue\BaseJob;
use rondodevs\toolkit\Toolkit;

class DeferredPurgeCacheJob extends BaseJob
{
    public int $elementId;
    public string $elementType; // Entry::class o Asset::class
    public array $elementTags = [];

    protected function defaultDescription(): string
    {
        return 'Deferred Nuxt cache purge';
    }

    public function execute($queue): void
    {
        if (Toolkit::getInstance()->kvCache->isEndpointUnreachable()) {
            Craft::info('DeferredPurgeCacheJob: skipped, Nuxt cache endpoint currently marked unreachable.', __METHOD__);
            return;
        }

        if (count($this->elementTags) > 0) {
            self::sendPurge($this->elementTags);
            return;
        }

        $element = $this->elementType::find()->id($this->elementId)->one();

        if (!$element) {
            Craft::warning("DeferredPurgeCacheJob: elemento non trovato (ID {$this->elementId})", __METHOD__);
            return;
        }

        $tags = self::collectTags($element);
        self::sendPurge($tags);
    }

    public static function collectTags($element): array
    {
        $tags = [];

        if ($element instanceof Entry) {
            $tags[] = "entry:{$element->id}";
            if ($element->section) {
                $tags[] = 'archive:' . $element->section->handle;
            }

            $relatedEntries = Entry::find()->relatedTo($element)->ids();
            $relatedAssets = Asset::find()->relatedTo($element)->ids();

            foreach ($relatedEntries as $id) {
                $tags[] = "entry:$id";
            }
            foreach ($relatedAssets as $id) {
                $tags[] = "asset:$id";
            }
        }

        if ($element instanceof Asset) {
            $tags[] = "asset:{$element->id}";
            if ($element->volume && $element->volume->id) {
                $tags[] = "archive:asset:{$element->volume->id}";
            }

            $relatedEntries = Entry::find()->relatedTo($element)->ids();
            foreach ($relatedEntries as $id) {
                $tags[] = "entry:$id";
            }
        }

        return array_values(array_unique($tags));
    }

    private static function sendPurge(array $tags): void
    {
        if ($tags === []) {
            Craft::info('DeferredPurgeCacheJob: no tags to send.', __METHOD__);
            return;
        }

        try {
            Toolkit::getInstance()->kvCache->purgeTags($tags);
        } catch (\Throwable $e) {
            Craft::error('DeferredPurgeCacheJob: purge failed - ' . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }
}
