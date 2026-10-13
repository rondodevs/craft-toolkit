<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\base\Component;
use craft\helpers\FileHelper;
use craft\utilities\ClearCaches;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\base\InvalidArgumentException;

/**
 * Emergency purges across every cache layer: Craft, the Nuxt KV cache and Cloudflare.
 */
class PurgeService extends Component
{
    /**
     * Clears every cache listed in Craft's "Caches" utility (data, GraphQL, compiled templates,
     * asset indexes, CP resources, temp files and any added by plugins), like `craft clear-caches/all`.
     */
    public function clearCraftCaches(): array
    {
        $failed = [];

        foreach (ClearCaches::cacheOptions() as $option) {
            $action = $option['action'];

            try {
                if (is_string($action)) {
                    FileHelper::clearDirectory($action);
                } elseif (isset($option['params'])) {
                    call_user_func_array($action, $option['params']);
                } else {
                    $action();
                }
            } catch (InvalidArgumentException) {
                // the directory does not exist, nothing to clear
            } catch (Throwable $e) {
                Craft::warning('PurgeService: could not clear "' . $option['key'] . '" - ' . $e->getMessage(), __METHOD__);
                $failed[] = $option['label'];
            }
        }

        if ($failed !== []) {
            return [
                'success' => false,
                'message' => 'Craft caches cleared, except: ' . implode(', ', $failed) . '.',
            ];
        }

        Craft::info('PurgeService: Craft caches cleared.', __METHOD__);

        return [
            'success' => true,
            'message' => 'Craft caches cleared.',
        ];
    }

    /**
     * Purges every cache, from the origin outwards: Craft first, so the KV cache refills from fresh
     * GraphQL data, then the KV cache, then Cloudflare (purged before KV it would refetch stale pages).
     * A failing Craft cache does not stop the KV and Cloudflare purges.
     */
    public function purgeAll(): array
    {
        $craft = $this->clearCraftCaches();

        try {
            $edge = Toolkit::getInstance()->kvCache->purgeEverything();
        } catch (Throwable $e) {
            Craft::error('PurgeService: KV cache flush failed - ' . $e->getMessage(), __METHOD__);
            $edge = [
                'success' => false,
                'message' => 'KV cache flush failed, Cloudflare not purged.',
            ];
        }

        if ($craft['success'] && $edge['success']) {
            return [
                'success' => true,
                'message' => 'Craft, KV and Cloudflare caches purged.',
            ];
        }

        return [
            'success' => false,
            'message' => $craft['message'] . ' ' . $edge['message'],
        ];
    }
}
