<?php

namespace rondodevs\toolkit\controllers;

use rondodevs\toolkit\cp\screens\CloudflareScreen;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

/**
 * Cloudflare purge settings, the edge cache check and the emergency purge of the whole zone.
 * The settings are stored with the KV cache settings, since every automatic Cloudflare purge
 * follows a KV cache purge.
 */
class CloudflareController extends ApiController
{
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            Toolkit::getInstance()->kvCache->saveSettings([
                'cloudflareEnabled' => (bool)$this->body('cloudflareEnabled', false),
                'cloudflareZoneId' => $this->body('cloudflareZoneId'),
                'cloudflareApiToken' => $this->body('cloudflareApiToken'),
            ]);

            return $this->asSaved('Cloudflare settings saved.', CloudflareScreen::props());
        }, 'Unable to save the Cloudflare settings.');
    }

    public function actionVerify(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(fn() => $this->asResult(Toolkit::getInstance()->kvCache->verifyCloudflare()), 'Cloudflare check failed.');
    }

    /**
     * Checks the frontend's CDN headers, Cloudflare's edge cache and the zone's Cache Rule.
     */
    public function actionCheckEdgeCache(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            $report = Toolkit::getInstance()->cloudflareCacheRule->check();

            return $this->asSuccess($report['message'], [
                'checks' => $report['checks'],
                'ruleAction' => $report['ruleAction'],
            ]);
        }, 'Edge cache check failed.');
    }

    /**
     * Creates the Cache Rule for the frontend's host, or fixes the existing one: never a second one.
     */
    public function actionCreateCacheRule(): Response
    {
        $this->requirePostRequest();

        return $this->asResult(Toolkit::getInstance()->cloudflareCacheRule->createRule());
    }

    /**
     * The zone's Cache Rules, for the modal on the Cloudflare screen.
     */
    public function actionListCacheRules(): Response
    {
        $this->requirePostRequest();
        $result = Toolkit::getInstance()->cloudflareCacheRule->listRules();

        return $this->asResult($result, [
            'host' => $result['host'] ?? null,
            'rules' => $result['rules'] ?? [],
        ]);
    }

    /**
     * Emergency purge of the whole Cloudflare zone only, leaving the KV cache alone.
     */
    public function actionPurgeEverything(): Response
    {
        $this->requirePostRequest();

        return $this->asResult(Toolkit::getInstance()->kvCache->purgeCloudflareEverything());
    }
}
