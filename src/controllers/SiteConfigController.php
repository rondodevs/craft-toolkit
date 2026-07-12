<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class SiteConfigController extends Controller
{
    public function actionSave(): ?Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $siteName = Craft::$app->getRequest()->getBodyParam('siteName');
        $siteUrl = Craft::$app->getRequest()->getBodyParam('siteUrl');

        try {
            Toolkit::getInstance()->siteConfig->saveOverrides($siteName, $siteUrl);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new BadRequestHttpException('Unable to save Toolkit site Config.', 0, $e);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Site Config overrides saved.'));

        return $this->redirectToPostedUrl();
    }
}
