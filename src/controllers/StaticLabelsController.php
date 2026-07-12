<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class StaticLabelsController extends Controller
{
    public function actionSave(): ?Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $labels = Craft::$app->getRequest()->getBodyParam('labels', []);

        try {
            Toolkit::getInstance()->staticLabels->saveOverrides(is_array($labels) ? $labels : []);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new BadRequestHttpException('Unable to save Toolkit static labels.', 0, $e);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Static labels saved.'));

        return $this->redirectToPostedUrl();
    }
}