<?php

namespace rondodevs\toolkit\controllers;

use rondodevs\toolkit\cp\screens\UnusedAssetsScreen;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

class UnusedAssetsController extends ApiController
{
    public function actionFind(): Response
    {
        $this->requirePostRequest();

        return $this->asJson(UnusedAssetsScreen::result((int)$this->request->getRequiredBodyParam('volumeId')));
    }

    /**
     * Moves the ticked assets to the trash, if they are still unused, and lists the volume again.
     */
    public function actionTrash(): Response
    {
        $this->requirePostRequest();

        $volumeId = (int)$this->request->getRequiredBodyParam('volumeId');
        $trashed = Toolkit::getInstance()->unusedAssets->trash($volumeId, array_map('intval', (array)$this->body('assetIds', [])));

        return $this->asSuccess($trashed === 0
            ? 'No assets moved to the trash.'
            : "$trashed assets moved to the trash. They can be restored from the trash in the Assets index.", [
            'result' => UnusedAssetsScreen::result($volumeId),
        ]);
    }
}
