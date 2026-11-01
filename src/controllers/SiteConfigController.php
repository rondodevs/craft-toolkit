<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use rondodevs\toolkit\cp\screens\SiteConfigScreen;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

class SiteConfigController extends ApiController
{
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            Toolkit::getInstance()->siteConfig->saveOverrides($this->body('siteName'), $this->body('siteUrl'));
            Craft::$app->getGql()->flushCaches();
            Toolkit::getInstance()->kvCache->purgeAfterSave(['SiteConfig']);

            return $this->asSaved('Site Config overrides saved.', SiteConfigScreen::props());
        }, 'Unable to save the Site Config.');
    }
}
