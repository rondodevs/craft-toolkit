<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use rondodevs\toolkit\cp\screens\OrgSchemaScreen;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

class OrgSchemaController extends ApiController
{
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            Toolkit::getInstance()->orgSchema->saveOverrides((array)$this->body('orgSchema', []));
            Craft::$app->getGql()->flushCaches();
            Toolkit::getInstance()->kvCache->purgeAfterSave(['OrgSchema']);

            return $this->asSaved('Org schema saved.', OrgSchemaScreen::props());
        }, 'Unable to save the org schema.');
    }

    /**
     * The logo picked in Craft's asset modal, as the screen shows it.
     */
    public function actionLogo(): Response
    {
        $this->requirePostRequest();
        $logo = OrgSchemaScreen::asset((int)$this->request->getRequiredBodyParam('id'));

        return $logo ? $this->asJson($logo) : $this->asFailure('Choose an image.');
    }
}
