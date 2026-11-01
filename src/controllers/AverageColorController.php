<?php

namespace rondodevs\toolkit\controllers;

use rondodevs\toolkit\cp\screens\AverageColorScreen;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

class AverageColorController extends ApiController
{
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            Toolkit::getInstance()->averageColor->saveSettings([
                'enabled' => (bool)$this->body('enabled', false),
                'volumeIds' => (array)$this->body('volumeIds', []),
            ]);

            return $this->asSaved('Average color settings saved.', AverageColorScreen::props());
        }, 'Unable to save the average color settings.');
    }

    public function actionCreateField(): Response
    {
        $this->requirePostRequest();
        $volumeId = (int)$this->request->getRequiredBodyParam('volumeId');

        return $this->attempt(
            fn() => $this->asResult(Toolkit::getInstance()->averageColor->ensureFieldOnVolume($volumeId), ['props' => AverageColorScreen::props()]),
            'Unable to create the averageColor field.',
        );
    }
}
