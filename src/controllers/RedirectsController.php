<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use rondodevs\toolkit\cp\screens\RedirectsScreen;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

class RedirectsController extends ApiController
{
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            $id = $this->body('id');
            Toolkit::getInstance()->redirects->save((array)$this->body('redirect', []), $id ? (int)$id : null);

            return $this->asSaved('Redirect saved.', RedirectsScreen::props());
        }, 'Unable to save the redirect.');
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            $count = Toolkit::getInstance()->redirects->delete((array)$this->body('ids', []));

            return $this->asSaved($count === 1 ? 'Redirect deleted.' : "$count redirects deleted.", RedirectsScreen::props());
        }, 'Unable to delete the redirects.');
    }

    public function actionReorder(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            Toolkit::getInstance()->redirects->reorder((array)$this->body('ids', []));

            return $this->asSaved('Order saved.', RedirectsScreen::props());
        }, 'Unable to save the order.');
    }

    public function actionSettings(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            if (!Craft::$app->getConfig()->getGeneral()->allowAdminChanges) {
                throw new \InvalidArgumentException('Admin changes are off in this environment: change it in development.');
            }

            Toolkit::getInstance()->redirects->setAutoEnabled((bool)$this->body('auto', true));

            return $this->asSaved('Settings saved.', RedirectsScreen::props());
        }, 'Unable to save the settings.');
    }

    public function actionImport(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            $result = Toolkit::getInstance()->redirects->importCsv((string)$this->body('csv', ''));

            return $this->asSaved("{$result['added']} added, {$result['skipped']} skipped (already there, or invalid).", RedirectsScreen::props());
        }, 'Unable to import the CSV.');
    }

    public function actionExport(): Response
    {
        return Craft::$app->getResponse()->sendContentAsFile(Toolkit::getInstance()->redirects->exportCsv(), 'redirects.csv', [
            'mimeType' => 'text/csv',
        ]);
    }

    /**
     * Which redirect a URL gets, as the frontend would ask.
     */
    public function actionTest(): Response
    {
        $this->requirePostRequest();

        return $this->attempt(function() {
            $url = trim((string)$this->body('url', ''));
            $sites = Craft::$app->getSites();
            $site = $sites->getSiteById((int)$this->body('siteId')) ?? $sites->getPrimarySite();

            if (!str_contains($url, '://')) {
                $url = rtrim($site->getBaseUrl() ?? 'https://example.com', '/') . '/' . ltrim($url, '/');
            }

            return $this->asJson(['result' => Toolkit::getInstance()->redirects->resolve($url, $site), 'url' => $url]);
        }, 'Unable to test the URL.');
    }
}
