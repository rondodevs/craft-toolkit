<?php

namespace rondodevs\toolkit\controllers;

use rondodevs\toolkit\cp\screens\FrontendScreen;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

class FrontendController extends ApiController
{
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            Toolkit::getInstance()->frontendConfig->saveSettings([
                'dataCache' => (bool)$this->body('dataCache', false),
                'routeCache' => (bool)$this->body('routeCache', false),
                'cdnCache' => (bool)$this->body('cdnCache', false),
                'routeCacheMaxAge' => $this->body('routeCacheMaxAge'),
                'routeCacheExclude' => $this->body('routeCacheExclude'),
                'redirects' => (bool)$this->body('redirects', false),
                'llms' => (bool)$this->body('llms', false),
                'internalUrl' => $this->body('internalUrl'),
            ]);

            return $this->asSaved('Frontend settings saved.', FrontendScreen::props());
        }, 'Unable to save the frontend settings.');
    }
}
