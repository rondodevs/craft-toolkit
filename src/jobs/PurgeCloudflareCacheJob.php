<?php

namespace rondodevs\toolkit\jobs;

use craft\queue\BaseJob;
use rondodevs\toolkit\Toolkit;

/**
 * Purges the Cloudflare edge cache after the Nuxt cache, see KvCacheService::queueCloudflarePurge().
 */
class PurgeCloudflareCacheJob extends BaseJob
{
    public array $tags = [];
    public bool $everything = false;

    protected function defaultDescription(): string
    {
        return $this->everything ? 'Cloudflare cache purge (everything)' : 'Cloudflare cache purge';
    }

    public function execute($queue): void
    {
        Toolkit::getInstance()->kvCache->purgeCloudflare($this->tags, $this->everything);
    }
}
