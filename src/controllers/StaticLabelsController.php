<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use rondodevs\toolkit\cp\screens\StaticLabelsScreen;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

class StaticLabelsController extends ApiController
{
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            Toolkit::getInstance()->staticLabels->saveOverrides((array)$this->body('labels', []));
            Craft::$app->getGql()->flushCaches();
            // Labels show up on every page: no single tag covers them
            Toolkit::getInstance()->kvCache->purgeAfterSave(null);

            return $this->asSaved('Static labels saved.', StaticLabelsScreen::props());
        }, 'Unable to save the static labels.');
    }
}
