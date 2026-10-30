<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

class UnusedAssetsController extends Controller
{
    /**
     * Moves the ticked assets to the trash, if they are still unused.
     */
    public function actionTrash(): ?Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $trashed = Toolkit::getInstance()->unusedAssets->trash(
            (int)$request->getRequiredBodyParam('volumeId'),
            array_map('intval', (array)$request->getBodyParam('assetIds', [])),
        );

        Craft::$app->getSession()->setNotice(Craft::t('app', $trashed === 0
            ? 'No assets moved to the trash.'
            : "{$trashed} assets moved to the trash. They can be restored from the trash in the Assets index."));

        return $this->redirectToPostedUrl();
    }
}
