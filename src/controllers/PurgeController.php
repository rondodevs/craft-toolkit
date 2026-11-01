<?php

namespace rondodevs\toolkit\controllers;

use rondodevs\toolkit\Toolkit;
use yii\web\Response;

class PurgeController extends ApiController
{
    /**
     * Emergency purge of Craft's caches, the KV cache and the whole Cloudflare zone, right away.
     */
    public function actionAll(): Response
    {
        $this->requirePostRequest();

        return $this->asResult(Toolkit::getInstance()->purge->purgeAll());
    }

    public function actionCraft(): Response
    {
        $this->requirePostRequest();

        return $this->asResult(Toolkit::getInstance()->purge->clearCraftCaches());
    }
}
