<?php

namespace rondodevs\toolkit\handlers;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\ElementHelper;
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
                } catch (\Throwable $ex) {
                    Craft::error('KVCacheHandlers: errore push job entry: ' . $ex->getMessage(), __METHOD__);
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
    }
}
