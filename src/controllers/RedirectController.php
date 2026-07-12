<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\helpers\App;
use yii\web\Controller;

class RedirectController extends Controller
{
  public function actionIndex()
  {
    $isDev = App::devMode();

    // check if request is GET
    if (Craft::$app->request->isGet) {
      if ($isDev) {
        return $this->redirect('/admin');
      }
      // redirect to admin page
      $frontendUrl = App::env('CRAFT_FRONTEND_URL') ?? 'http://localhost:3000';
      return $this->redirect($frontendUrl);
    }

    // redirect to home page
    return $this->redirect('/');
  }
}
