<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class KvCacheController extends Controller
{
    public function actionSave(): ?Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        try {
            Toolkit::getInstance()->kvCache->saveSettings([
                'enabled' => (bool)Craft::$app->getRequest()->getBodyParam('enabled', false),
                'frontendUrl' => Craft::$app->getRequest()->getBodyParam('frontendUrl'),
                'authToken' => Craft::$app->getRequest()->getBodyParam('authToken'),
                'authHeaderName' => Craft::$app->getRequest()->getBodyParam('authHeaderName'),
                'requestTimeout' => Craft::$app->getRequest()->getBodyParam('requestTimeout'),
                'connectTimeout' => Craft::$app->getRequest()->getBodyParam('connectTimeout'),
            ]);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new BadRequestHttpException('Unable to save Toolkit KV cache settings.', 0, $e);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'KV cache settings saved.'));

        return $this->redirectToPostedUrl();
    }

    public function actionFlush(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        try {
            $result = Toolkit::getInstance()->kvCache->flushAll();

            if (!$result['success']) {
                return $this->asFailure($result['message']);
            }

            return $this->asSuccess($result['message']);
        } catch (Throwable $e) {
            Craft::error('KV cache flush failed: ' . $e->getMessage(), __METHOD__);
            return $this->asFailure('KV cache flush failed.');
        }
    }

    public function actionPurgeTags(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $tags = Craft::$app->getRequest()->getBodyParam('tags', []);

        if (is_string($tags)) {
            $tags = preg_split('/[\r\n,]+/', $tags) ?: [];
        }

        if (!is_array($tags)) {
            $tags = [];
        }

        try {
            $result = Toolkit::getInstance()->kvCache->purgeTags($tags);

            if (!$result['success']) {
                return $this->asFailure($result['message']);
            }

            return $this->asSuccess($result['message']);
        } catch (Throwable $e) {
            Craft::error('KV cache tag purge failed: ' . $e->getMessage(), __METHOD__);
            return $this->asFailure('KV cache tag purge failed.');
        }
    }

    public function actionPurgeKeys(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $cacheType = (string)Craft::$app->getRequest()->getBodyParam('cacheType', 'data');
        $keys = Craft::$app->getRequest()->getBodyParam('keys', []);

        if (is_string($keys)) {
            $keys = preg_split('/[\r\n,]+/', $keys) ?: [];
        }

        if (!is_array($keys)) {
            $keys = [];
        }

        try {
            $result = Toolkit::getInstance()->kvCache->purgeKeys($cacheType, $keys);

            if (!$result['success']) {
                return $this->asFailure($result['message']);
            }

            return $this->asSuccess($result['message']);
        } catch (Throwable $e) {
            Craft::error('KV cache key purge failed: ' . $e->getMessage(), __METHOD__);
            return $this->asFailure('KV cache key purge failed.');
        }
    }

    public function actionCheck(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        try {
            $result = Toolkit::getInstance()->kvCache->checkStats();

            if (!$result['success']) {
                return $this->asFailure($result['message'], $result);
            }

            return $this->asSuccess($result['message'], $result);
        } catch (Throwable $e) {
            Craft::error('KV cache stats check failed: ' . $e->getMessage(), __METHOD__);
            return $this->asFailure('KV cache stats check failed.');
        }
    }
}