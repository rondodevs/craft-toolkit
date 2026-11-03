<?php

namespace rondodevs\toolkit\controllers;

use rondodevs\toolkit\Toolkit;
use yii\web\Response;

/**
 * Toolkit → Health: runs every check of HealthService, see there.
 */
class HealthController extends ApiController
{
    public function actionCheck(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            $report = Toolkit::getInstance()->health->run();

            return $this->asSuccess($report['message'], ['groups' => $report['groups']]);
        }, 'Health check failed.');
    }
}
