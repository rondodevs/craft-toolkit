<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use yii\web\BadRequestHttpException;
use yii\web\Response;

/**
 * The redirects for the frontend: Craft is headless and never serves the
 * pages, so the frontend asks before rendering (nuxt-craft-toolkit).
 *
 * GET actions/toolkit/redirects-api/resolve?url=https://site.com/old/&site=en
 * → `{"id": 3, "to": "https://site.com/new", "type": 301}` or `null`
 * POST actions/toolkit/redirects-api/hit {id} after the frontend redirected
 */
class RedirectsApiController extends Controller
{
    private const MAX_URL_LENGTH = 2048;

    protected array|bool|int $allowAnonymous = ['resolve', 'hit'];

    public function beforeAction($action): bool
    {
        // Called server to server, without a session
        if ($action->id === 'hit') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    public function actionResolve(): Response
    {
        $url = $this->request->getRequiredQueryParam('url');

        if (!is_string($url) || strlen($url) > self::MAX_URL_LENGTH || !isset(parse_url($url)['host'])) {
            throw new BadRequestHttpException('Invalid url.');
        }

        $sites = Craft::$app->getSites();
        $site = $sites->getSiteByHandle((string)$this->request->getQueryParam('site', '')) ?? $sites->getPrimarySite();

        // Not cached over HTTP: the frontend caches answers until Craft clears them
        $response = $this->asJson(Toolkit::getInstance()->redirects->resolve($url, $site));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    public function actionHit(): Response
    {
        $this->requirePostRequest();

        $id = (int)$this->request->getBodyParam('id');

        if ($id > 0) {
            Toolkit::getInstance()->redirects->recordHit($id);
        }

        return $this->asJson(null);
    }
}
