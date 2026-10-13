<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Cloudflare purge settings, and the emergency purge of the whole zone. The settings are stored
 * with the KV cache settings, since every automatic Cloudflare purge follows a KV cache purge.
 */
class CloudflareController extends Controller
{
    public function actionSave(): ?Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        try {
            Toolkit::getInstance()->kvCache->saveSettings([
                'cloudflareEnabled' => (bool)Craft::$app->getRequest()->getBodyParam('cloudflareEnabled', false),
                'cloudflareZoneId' => Craft::$app->getRequest()->getBodyParam('cloudflareZoneId'),
                'cloudflareApiToken' => Craft::$app->getRequest()->getBodyParam('cloudflareApiToken'),
            ]);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new BadRequestHttpException('Unable to save Toolkit Cloudflare settings.', 0, $e);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Cloudflare settings saved.'));

        return $this->redirectToPostedUrl();
    }

    public function actionVerify(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        try {
            $result = Toolkit::getInstance()->kvCache->verifyCloudflare();

            if (!$result['success']) {
                return $this->asFailure($result['message']);
            }

            return $this->asSuccess($result['message']);
        } catch (Throwable $e) {
            Craft::error('Cloudflare check failed: ' . $e->getMessage(), __METHOD__);
            return $this->asFailure('Cloudflare check failed.');
        }
    }

    /**
     * Checks the frontend's CDN headers, Cloudflare's edge cache and the zone's Cache Rule.
     */
    public function actionCheckEdgeCache(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        try {
            $report = Toolkit::getInstance()->cloudflareCacheRule->check();
        } catch (Throwable $e) {
            Craft::error('Edge cache check failed: ' . $e->getMessage(), __METHOD__);
            return $this->asFailure('Edge cache check failed.');
        }

        return $this->asSuccess($report['message'], [
            'checks' => $report['checks'],
            'ruleAction' => $report['ruleAction'],
        ]);
    }

    /**
     * Creates the Cache Rule for the frontend's host, or fixes the existing one: never a second one.
     */
    public function actionCreateCacheRule(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $result = Toolkit::getInstance()->cloudflareCacheRule->createRule();

        if (!$result['success']) {
            return $this->asFailure($result['message']);
        }

        return $this->asSuccess($result['message']);
    }

    /**
     * The zone's Cache Rules, for the modal on the Cloudflare screen.
     */
    public function actionListCacheRules(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $result = Toolkit::getInstance()->cloudflareCacheRule->listRules();

        if (!$result['success']) {
            return $this->asFailure($result['message'], ['tokenUrl' => $result['tokenUrl'] ?? null]);
        }

        return $this->asSuccess($result['message'], [
            'host' => $result['host'],
            'rules' => $result['rules'],
        ]);
    }

    /**
     * Emergency purge of the whole Cloudflare zone only, leaving the KV cache alone.
     */
    public function actionPurgeEverything(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $result = Toolkit::getInstance()->kvCache->purgeCloudflareEverything();

        if (!$result['success']) {
            return $this->asFailure($result['message']);
        }

        return $this->asSuccess($result['message']);
    }
}
