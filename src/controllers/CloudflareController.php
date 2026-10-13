<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * Cloudflare purge settings. They are stored with the KV cache settings, since every
 * Cloudflare purge follows a KV cache purge.
 */
class CloudflareController extends Controller
{
    public function actionSave(): ?Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        try {
            Toolkit::getInstance()->kvCache->saveSettings([
                'cloudflareEnabled' => (bool)Craft::$app->getRequest()->getBodyParam('cloudflareEnabled', false),
                'cloudflareZoneId' => Craft::$app->getRequest()->getBodyParam('cloudflareZoneId'),
                'cloudflareApiToken' => Craft::$app->getRequest()->getBodyParam('cloudflareApiToken'),
            ]);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new BadRequestHttpException('Unable to save Toolkit Cloudflare settings.', 0, $e);
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Cloudflare settings saved.'));

        return $this->redirectToPostedUrl();
    }

    public function actionVerify(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        try {
            $result = Toolkit::getInstance()->kvCache->verifyCloudflare();

            if (!$result['success']) {
                return $this->asFailure($result['message']);
            }

            return $this->asSuccess($result['message']);
        } catch (Throwable $e) {
            Craft::error('Cloudflare check failed: ' . $e->getMessage(), __METHOD__);
            return $this->asFailure('Cloudflare check failed.');
        }
    }
}
