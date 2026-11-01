<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

/**
 * The site root: Craft only serves the CP and GraphQL, visitors belong on the frontend.
 */
class SiteRedirectController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];

    public function actionIndex(): Response
    {
        if (!$this->request->getIsGet()) {
            return $this->redirect('/');
        }

        // In dev mode, and for anyone logged in, the root leads to the CP
        if (App::devMode() || !Craft::$app->getUser()->getIsGuest()) {
            return $this->redirect('/admin');
        }

        $settings = Toolkit::getInstance()->redirect->getResolvedSettings();

        return $this->redirect($settings['enabled'] ? $settings['frontendUrl'] : '/');
    }
}
