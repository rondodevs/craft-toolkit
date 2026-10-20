<?php

namespace rondodevs\toolkit\jobs;

use craft\queue\BaseJob;
use rondodevs\toolkit\Toolkit;

/**
 * Sends the buffered Cloudflare purges in one go, see KvCacheService::queueCloudflarePurge().
 */
class PurgeCloudflareCacheJob extends BaseJob
{
    // Only set on jobs queued before purges were buffered: they are moved into the buffer.
    public array $tags = [];
    public bool $everything = false;

    protected function defaultDescription(): string
    {
        return 'Cloudflare cache purge';
    }

    public function execute($queue): void
    {
        $kvCache = Toolkit::getInstance()->kvCache;

        if ($this->everything || $this->tags !== []) {
            $kvCache->queueCloudflarePurge($this->everything ? null : $this->tags);
            return;
        }

        $kvCache->flushPendingCloudflarePurge();
    }
}
