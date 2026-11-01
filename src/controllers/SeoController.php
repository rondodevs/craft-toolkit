<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use rondodevs\toolkit\cp\screens\SeoScreen;
use rondodevs\toolkit\fields\SeoField;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\web\Response;

class SeoController extends ApiController
{
    protected array $editorActions = ['image-preview'];

    /**
     * The social previews' image when the editor picks another one.
     */
    public function actionImagePreview(): Response
    {
        $this->requireAcceptsJson();

        $asset = Craft::$app->getAssets()->getAssetById((int)$this->request->getRequiredBodyParam('id'));

        if ($asset === null) {
            return $this->asJson(['image' => null]);
        }

        $this->requirePermission('viewAssets:' . $asset->getVolume()->uid);

        return $this->asJson(['image' => SeoField::previewImage($asset)]);
    }

    public function actionMigrateEther(): Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        try {
            $result = Toolkit::getInstance()->seo->migrateFromEther();
        } catch (Throwable $e) {
            return $this->asFailure($e->getMessage());
        }

        return $this->asSaved(Craft::t('app', 'Converted {count} field(s) to Toolkit SEO{uninstalled}.', [
            'count' => count($result['migrated']),
            'uninstalled' => $result['uninstalled'] ? ' and uninstalled Ether SEO' : '',
        ]), SeoScreen::props());
    }
}
