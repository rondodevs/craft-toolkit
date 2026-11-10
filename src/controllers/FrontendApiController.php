<?php

namespace rondodevs\toolkit\controllers;

use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

/**
 * The frontend's runtime settings (Toolkit → Frontend), read by layer-craft at startup and every minute.
 *
 * GET actions/toolkit/frontend-api/config
 * → `{"version": 1, "cache": {"data": true, ...}, "redirects": true, "llms": true, "internalUrl": "http://…:8080", "publicKey": "…"}`
 *
 * Public on purpose, like the public key: switches and addresses, nothing secret.
 */
class FrontendApiController extends Controller
{
    protected array|bool|int $allowAnonymous = ['config'];

    public function actionConfig(): Response
    {
        $response = $this->asJson(Toolkit::getInstance()->frontendConfig->publicConfig());
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
