<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class AverageColorController extends Controller
{
    public function actionSave(): ?Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        try {
            Toolkit::getInstance()->averageColor->saveSettings([
                'enabled' => (bool)Craft::$app->getRequest()->getBodyParam('enabled', false),
                'volumeIds' => Craft::$app->getRequest()->getBodyParam('volumeIds', []),
            ]);
        } catch (Throwable $e) {
            throw new BadRequestHttpException('Unable to save Toolkit average color settings.', 0, $e);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Average color settings saved.'));

        return $this->redirectToPostedUrl();
    }

    public function actionCreateField(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $volumeId = (int)Craft::$app->getRequest()->getRequiredBodyParam('volumeId');

        try {
            $result = Toolkit::getInstance()->averageColor->ensureFieldOnVolume($volumeId);

            if (!$result['success']) {
                return $this->asFailure($result['message']);
            }

            return $this->asSuccess($result['message']);
        } catch (Throwable $e) {
            Craft::error('Average color field creation failed: ' . $e->getMessage(), __METHOD__);
            return $this->asFailure('Unable to create the averageColor field: ' . $e->getMessage());
        }
    }
}
