<?php

namespace rondodevs\toolkit\handlers;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\events\FieldEvent;
use craft\events\MoveElementEvent;
use craft\events\SiteEvent;
use craft\helpers\ElementHelper;
use craft\services\Fields;
use craft\services\Sites;
use craft\services\Structures;
use rondodevs\toolkit\fields\SeoField;
use rondodevs\toolkit\Toolkit;
use yii\base\Event;
use rondodevs\toolkit\jobs\DeferredPurgeCacheJob;

class KVCacheHandlers
{
    public static function register(): void
    {
        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function (Event $e): void {
                $entry = $e->sender;

                if (
                    !Toolkit::getInstance()->kvCache->isEnabled() ||
                    Toolkit::getInstance()->kvCache->isEndpointUnreachable() ||
                    !$entry ||
                    ElementHelper::isDraftOrRevision($entry)
                ) {
                    return;
                }

                try {
                    Craft::$app->queue->push(new DeferredPurgeCacheJob([
                        'elementId' => $entry->id,
                        'elementType' => Entry::class,
                    ]));
                    // nested (Matrix) entries have no dates of their own: their page's are enough
                    if ($entry->section) {
                        DeferredPurgeCacheJob::scheduleForDates($entry);
                    }
                } catch (\Throwable $ex) {
                    Craft::error('KVCacheHandlers: errore push job entry: ' . $ex->getMessage(), __METHOD__);
                }
            }
        );

        // moving an entry in a structure saves nothing, but changes the order of its lists
        Event::on(
            Structures::class,
            Structures::EVENT_AFTER_MOVE_ELEMENT,
            function (MoveElementEvent $e): void {
                $entry = $e->element;

                if (
                    !$entry instanceof Entry ||
                    !Toolkit::getInstance()->kvCache->isEnabled() ||
                    Toolkit::getInstance()->kvCache->isEndpointUnreachable() ||
                    ElementHelper::isDraftOrRevision($entry)
                ) {
                    return;
                }

                try {
                    Craft::$app->queue->push(new DeferredPurgeCacheJob([
                        'elementId' => $entry->id,
                        'elementType' => Entry::class,
                    ]));
                } catch (\Throwable $ex) {
                    Craft::error('KVCacheHandlers: errore push job structure move: ' . $ex->getMessage(), __METHOD__);
                }
            }
        );


        Event::on(
            Entry::class,
            Entry::EVENT_BEFORE_DELETE,
            function (Event $e): void {
                $entry = $e->sender;
                if (
                    !Toolkit::getInstance()->kvCache->isEnabled() ||
                    Toolkit::getInstance()->kvCache->isEndpointUnreachable() ||
                    !$entry ||
                    ElementHelper::isDraftOrRevision($entry)
                ) {
                    return;
                }
                try {
                    $tags = DeferredPurgeCacheJob::collectTags($entry);
                    Craft::$app->queue->push(new DeferredPurgeCacheJob([
                        'elementTags' => $tags,
                    ]));
                } catch (\Throwable $ex) {
                    Craft::error('KVCacheHandlers: errore push job entry delete: ' . $ex->getMessage(), __METHOD__);
                }
            }
        );

        Event::on(
            Asset::class,
            Asset::EVENT_AFTER_SAVE,
            function (Event $e): void {
                $asset = $e->sender;

                if (
                    !Toolkit::getInstance()->kvCache->isEnabled() ||
                    Toolkit::getInstance()->kvCache->isEndpointUnreachable() ||
                    !$asset ||
                    ElementHelper::isDraftOrRevision($asset)
                ) {
                    return;
                }

                try {
                    Craft::$app->queue->push(new DeferredPurgeCacheJob([
                        'elementId' => $asset->id,
                        'elementType' => Asset::class,
                    ]));
                } catch (\Throwable $ex) {
                    Craft::error('KVCacheHandlers: errore push job asset: ' . $ex->getMessage(), __METHOD__);
                }
            }
        );

        // tags collected before the delete: afterwards the asset and its relations are gone
        Event::on(
            Asset::class,
            Asset::EVENT_BEFORE_DELETE,
            function (Event $e): void {
                $asset = $e->sender;
                if (
                    !Toolkit::getInstance()->kvCache->isEnabled() ||
                    Toolkit::getInstance()->kvCache->isEndpointUnreachable() ||
                    !$asset
                ) {
                    return;
                }
                try {
                    Craft::$app->queue->push(new DeferredPurgeCacheJob([
                        'elementTags' => DeferredPurgeCacheJob::collectTags($asset),
                    ]));
                } catch (\Throwable $ex) {
                    Craft::error('KVCacheHandlers: errore push job asset delete: ' . $ex->getMessage(), __METHOD__);
                }
            }
        );

        // The SEO field's settings (title suffix, default image and robots, sources) and the site
        // (its name in the title suffix) end up in every page's SEO, under no element's tag.
        Event::on(
            Fields::class,
            Fields::EVENT_AFTER_SAVE_FIELD,
            function (FieldEvent $e): void {
                if ($e->field instanceof SeoField && !$e->isNew) {
                    Toolkit::getInstance()->kvCache->purgeAfterSave(null);
                }
            }
        );

        Event::on(
            Sites::class,
            Sites::EVENT_AFTER_SAVE_SITE,
            function (SiteEvent $e): void {
                if (!$e->isNew) {
                    Toolkit::getInstance()->kvCache->purgeAfterSave(null);
                }
            }
        );
    }
}
