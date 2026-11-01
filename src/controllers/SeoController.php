<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use rondodevs\toolkit\fields\SeoField;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\web\Response;

class SeoController extends Controller
{
    /**
     * The social previews' image when the editor picks another one.
     */
    public function actionImagePreview(): Response
    {
        $this->requireCpRequest();
        $this->requireAcceptsJson();

        $asset = Craft::$app->getAssets()->getAssetById((int)$this->request->getRequiredBodyParam('id'));

        if ($asset === null) {
            return $this->asJson(['image' => null]);
        }

        $this->requirePermission('viewAssets:' . $asset->getVolume()->uid);

        return $this->asJson(['image' => SeoField::previewImage($asset)]);
    }

    public function actionMigrateEther(): ?Response
    {
        $this->requireCpRequest();
        $this->requireAdmin();
        $this->requirePostRequest();

        try {
            $result = Toolkit::getInstance()->seo->migrateFromEther();
        } catch (Throwable $e) {
            Craft::$app->getSession()->setError($e->getMessage());

            return $this->redirectToPostedUrl();
        }

        Craft::$app->getSession()->setNotice(Craft::t('app', 'Converted {count} field(s) to Toolkit SEO{uninstalled}.', [
            'count' => count($result['migrated']),
            'uninstalled' => $result['uninstalled'] ? ' and uninstalled Ether SEO' : '',
        ]));

        return $this->redirectToPostedUrl();
    }
}
