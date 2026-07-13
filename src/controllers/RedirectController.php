<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\helpers\App;
use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class RedirectController extends Controller
{
  public function actionIndex()
  {
    $isDev = App::devMode();
    $settings = Toolkit::getInstance()->redirect->getResolvedSettings();

    // check if request is GET
    if (Craft::$app->request->isGet) {
      if ($isDev) {
        return $this->redirect('/admin');
      }

      if (!$settings['enabled']) {
        return $this->redirect('/');
      }

      return $this->redirect($settings['frontendUrl']);
    }

    // redirect to home page
    return $this->redirect('/');
  }

  public function actionSave(): ?Response
  {
    $this->requireCpRequest();
    $this->requireAdmin(false);
    $this->requirePostRequest();

    try {
      Toolkit::getInstance()->redirect->saveSettings([
        'enabled' => (bool)Craft::$app->getRequest()->getBodyParam('enabled', false),
        'frontendUrl' => Craft::$app->getRequest()->getBodyParam('frontendUrl'),
      ]);
    } catch (\InvalidArgumentException $e) {
      throw new BadRequestHttpException($e->getMessage(), 0, $e);
    } catch (Throwable $e) {
      throw new BadRequestHttpException('Unable to save Toolkit redirect settings.', 0, $e);
    }

    Craft::$app->getSession()->setNotice(Craft::t('app', 'Redirect settings saved.'));

    return $this->redirectToPostedUrl();
  }
}
