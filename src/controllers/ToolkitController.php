<?php

namespace rondodevs\toolkit\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use rondodevs\toolkit\utilities\AdvancedUtility;
use rondodevs\toolkit\utilities\AverageColorUtility;
use rondodevs\toolkit\utilities\CloudflareUtility;
use rondodevs\toolkit\utilities\KVCacheUtilities;
use rondodevs\toolkit\utilities\OrgSchemaUtility;
use rondodevs\toolkit\utilities\RedirectUtility;
use rondodevs\toolkit\utilities\SiteConfigUtility;
use rondodevs\toolkit\utilities\StaticLabelsUtility;
use rondodevs\toolkit\ToolkitSections;

class ToolkitController extends Controller
{
    public function actionIndex(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);

        return $this->redirect('toolkit/site-config');
    }

    public function actionAdvanced(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);

        return $this->renderToolkitScreen(
            'advanced',
            'Advanced',
            AdvancedUtility::contentHtml()
        );
    }

    public function actionSiteConfig(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);

        return $this->renderToolkitScreen(
            'site-config',
            'Site Config',
            SiteConfigUtility::contentHtml()
        );
    }

    public function actionKvCache(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);

        return $this->renderToolkitScreen(
            'kv-cache',
            'KV Cache',
            KVCacheUtilities::contentHtml()
        );
    }

    public function actionCloudflare(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);

        return $this->renderToolkitScreen(
            'cloudflare',
            'Cloudflare',
            CloudflareUtility::contentHtml()
        );
    }

    public function actionStaticLabels(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);

        return $this->renderToolkitScreen(
            'static-labels',
            'Static Labels',
            StaticLabelsUtility::contentHtml()
        );
    }

    public function actionAverageColor(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);

        return $this->renderToolkitScreen(
            'average-color',
            'Average Color',
            AverageColorUtility::contentHtml()
        );
    }

    public function actionRedirect(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);

        return $this->renderToolkitScreen(
            'redirect',
            'Redirect',
            RedirectUtility::contentHtml()
        );
    }

    public function actionOrgSchema(): Response
    {
        $this->requireCpRequest();
        $this->requireAdmin(false);

        return $this->renderToolkitScreen(
            'org-schema',
            'Org Schema',
            OrgSchemaUtility::contentHtml()
        );
    }

    private function renderToolkitScreen(string $subnavItem, string $sectionTitle, string $contentHtml): Response
    {
        $sections = [];

        foreach (ToolkitSections::all() as $id => $section) {
            $sections[$id] = $section + ['iconSvg' => ToolkitSections::iconSvg($id)];
        }

        return $this->asCpScreen()
            ->title('Toolkit')
            ->selectedSubnavItem($subnavItem)
            ->contentTemplate('toolkit/index', [
                'sectionTitle' => $sectionTitle,
                'contentHtml' => $contentHtml,
                'sections' => $sections,
                'activeSection' => $subnavItem,
            ]);
    }
}