<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class OrgSchemaController extends Controller
{
    public function actionSave(): ?Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $orgSchema = Craft::$app->getRequest()->getBodyParam('orgSchema', []);

        try {
            Toolkit::getInstance()->orgSchema->saveOverrides(is_array($orgSchema) ? $orgSchema : []);
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            throw new BadRequestHttpException('Unable to save Toolkit org schema.', 0, $e);
        }

        Craft::$app->getGql()->flushCaches();
        $this->purgeFrontendCache();

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Org schema saved.'));

        return $this->redirectToPostedUrl();
    }

    private function purgeFrontendCache(): void
    {
        $kvCache = Toolkit::getInstance()->kvCache;

        if (!$kvCache->isEnabled()) {
            return;
        }

        try {
            $kvCache->purgeTags(['OrgSchema']);
        } catch (Throwable $e) {
            Craft::warning('OrgSchemaController: unable to purge frontend cache after save - ' . $e->getMessage(), __METHOD__);
        }
    }
}
