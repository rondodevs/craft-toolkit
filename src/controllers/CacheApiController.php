<?php

namespace rondodevs\toolkit\controllers;

use craft\web\Controller;
use rondodevs\toolkit\support\RequestSigner;
use yii\web\Response;

/**
 * The public key the frontend checks Craft's signed cache API requests with (see RequestSigner).
 *
 * GET actions/toolkit/cache-api/public-key
 * → `{"algorithm": "ed25519", "version": "v1", "publicKey": "<base64>"}`
 *
 * Public on purpose: a public key proves nothing by itself, only Craft holds the private one.
 */
class CacheApiController extends Controller
{
    protected array|bool|int $allowAnonymous = ['public-key'];

    public function actionPublicKey(): Response
    {
        $response = $this->asJson([
            'algorithm' => 'ed25519',
            'version' => RequestSigner::VERSION,
            'publicKey' => RequestSigner::publicKey(),
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
