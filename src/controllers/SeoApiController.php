<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use rondodevs\toolkit\Toolkit;
use yii\web\Response;

/**
 * SEO data the frontend builds whole-site files from (nuxt-craft-toolkit).
 *
 * GET actions/toolkit/seo-api/llms?site=en
 * → `{"site": {"name", "url"}, "pages": [{"title", "url", "summary"}]}` for /llms.txt
 */
class SeoApiController extends Controller
{
    protected array|bool|int $allowAnonymous = ['llms'];

    public function actionLlms(): Response
    {
        $sites = Craft::$app->getSites();
        $site = $sites->getSiteByHandle((string)$this->request->getQueryParam('site', '')) ?? $sites->getPrimarySite();

        $response = $this->asJson([
            'site' => ['name' => $site->getName(), 'url' => $site->getBaseUrl()],
            'pages' => Toolkit::getInstance()->seo->llmsPages($site),
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
