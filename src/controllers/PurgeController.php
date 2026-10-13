<?php

namespace rondodevs\toolkit\controllers;

use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

class PurgeController extends Controller
{
    /**
     * Emergency purge of Craft's caches, the KV cache and the whole Cloudflare zone, right away.
     */
    public function actionAll(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $result = Toolkit::getInstance()->purge->purgeAll();

        if (!$result['success']) {
            return $this->asFailure($result['message']);
        }

        return $this->asSuccess($result['message']);
    }

    public function actionCraft(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $result = Toolkit::getInstance()->purge->clearCraftCaches();

        if (!$result['success']) {
            return $this->asFailure($result['message']);
        }

        return $this->asSuccess($result['message']);
    }
}
