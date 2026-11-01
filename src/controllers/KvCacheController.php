<?php

namespace rondodevs\toolkit\controllers;

use rondodevs\toolkit\cp\screens\KvCacheScreen;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

class KvCacheController extends ApiController
{
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            Toolkit::getInstance()->kvCache->saveSettings([
                'enabled' => (bool)$this->body('enabled', false),
                'frontendUrl' => $this->body('frontendUrl'),
                'authToken' => $this->body('authToken'),
                'authHeaderName' => $this->body('authHeaderName'),
                'requestTimeout' => $this->body('requestTimeout'),
                'connectTimeout' => $this->body('connectTimeout'),
            ]);

            return $this->asSaved('KV cache settings saved.', KvCacheScreen::props());
        }, 'Unable to save the KV cache settings.');
    }

    public function actionFlush(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(fn() => $this->asResult(Toolkit::getInstance()->kvCache->flushAll()), 'KV cache flush failed.');
    }

    /**
     * Emergency flush of the KV cache only, without the Cloudflare purge that follows flushAll().
     */
    public function actionFlushKv(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(fn() => $this->asResult(Toolkit::getInstance()->kvCache->flushKv()), 'KV cache flush failed.');
    }

    public function actionPurgeTags(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(
            fn() => $this->asResult(Toolkit::getInstance()->kvCache->purgeTags($this->list('tags'))),
            'KV cache tag purge failed.',
        );
    }

    public function actionPurgeKeys(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(
            fn() => $this->asResult(Toolkit::getInstance()->kvCache->purgeKeys((string)$this->body('cacheType', 'data'), $this->list('keys'))),
            'KV cache key purge failed.',
        );
    }

    public function actionCheck(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            $result = Toolkit::getInstance()->kvCache->checkStats();

            return $this->asResult($result, ['details' => $result['details'] ?? null]);
        }, 'KV cache stats check failed.');
    }

    /**
     * A list posted as an array, or as one per line / comma separated.
     *
     * @return string[]
     */
    private function list(string $name): array
    {
        $value = $this->body($name, []);

        if (is_string($value)) {
            return preg_split('/[\r\n,]+/', $value) ?: [];
        }

        return is_array($value) ? $value : [];
    }
}
