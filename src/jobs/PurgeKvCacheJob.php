<?php

namespace rondodevs\toolkit\jobs;

use craft\queue\BaseJob;
use rondodevs\toolkit\Toolkit;

/**
 * Sends the buffered frontend (Nuxt KV) purge in one request, see KvCacheService::queuePurgeTags().
 * Always tries, even with the endpoint marked unreachable: one request per batch can't flood it,
 * and a failed one puts the tags back in the buffer for the next save's job.
 */
class PurgeKvCacheJob extends BaseJob
{
    protected function defaultDescription(): string
    {
        return 'Nuxt cache purge';
    }

    public function execute($queue): void
    {
        Toolkit::getInstance()->kvCache->flushPendingKvPurge();
    }
}
