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
            // a nested (Matrix) entry saved on its own: the page showing it is its owner's
            array_push($tags, ...self::ownerTags($element));

            $relatedAssets = Asset::find()->relatedTo($element)->ids();

            array_push($tags, ...self::entryTags(Entry::find()->relatedTo($element)->all()));
            foreach ($relatedAssets as $id) {
                $tags[] = "asset:$id";
            }
        }

        if ($element instanceof Asset) {
            $tags[] = "asset:{$element->id}";
            if ($element->volume && $element->volume->id) {
                $tags[] = "archive:asset:{$element->volume->id}";
            }

            array_push($tags, ...self::entryTags(Entry::find()->relatedTo($element)->all()));
        }

        return array_values(array_unique($tags));
    }

    /**
     * `entry:{id}` for each entry and for the owners it is nested in. In Craft 5 a relation belongs
     * to the nested (Matrix) entry holding the field, so `relatedTo()` finds the block, not the page:
     * the frontend caches the page under the page's id.
     *
     * @param Entry[] $entries
     * @return string[]
     */
    private static function entryTags(array $entries): array
    {
        $tags = [];

        foreach ($entries as $entry) {
            $tags[] = "entry:{$entry->id}";
            array_push($tags, ...self::ownerTags($entry));
        }

        return $tags;
    }

    /**
     * `entry:{id}` for every owner up the nesting chain (block → column → page).
     *
     * @return string[]
     */
    private static function ownerTags(Entry $entry): array
    {
        $tags = [];
        $seen = [$entry->id => true];
        $owner = $entry->getPrimaryOwner();

        while ($owner !== null && !isset($seen[$owner->id])) {
            $seen[$owner->id] = true;
            $tags[] = "entry:{$owner->id}";
            $owner = $owner instanceof Entry ? $owner->getPrimaryOwner() : null;
        }

        return $tags;
    }

    private static function sendPurge(array $tags): void
    {
        if ($tags === []) {
            Craft::info('DeferredPurgeCacheJob: no tags to send.', __METHOD__);
            return;
        }

        // buffered: a page save and its nested entries' saves reach the frontend as one request
        try {
            Toolkit::getInstance()->kvCache->queuePurgeTags($tags);
        } catch (\Throwable $e) {
            Craft::error('DeferredPurgeCacheJob: could not buffer the purge - ' . $e->getMessage(), __METHOD__);
            throw $e;
        }
    }
}
