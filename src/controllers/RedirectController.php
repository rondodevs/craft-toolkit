<?php

namespace rondodevs\toolkit\controllers;

use rondodevs\toolkit\cp\screens\RedirectScreen;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

class RedirectController extends ApiController
{
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            Toolkit::getInstance()->redirect->saveSettings([
                'enabled' => (bool)$this->body('enabled', false),
                'frontendUrl' => $this->body('frontendUrl'),
            ]);

            return $this->asSaved('Redirect settings saved.', RedirectScreen::props());
        }, 'Unable to save the redirect settings.');
    }
}
