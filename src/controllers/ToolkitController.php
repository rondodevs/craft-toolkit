<?php

namespace rondodevs\toolkit\controllers;

use craft\helpers\UrlHelper;
use craft\web\Controller;
use craft\web\Response;
use rondodevs\toolkit\ToolkitSections;
use rondodevs\toolkit\web\Island;
use yii\web\NotFoundHttpException;

/**
 * The Toolkit's CP pages: each one the Vue screen of its section, with the nav bar.
 */
class ToolkitController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requireCpRequest();
        $this->requireAdmin(false);

        return true;
    }

    public function actionIndex(): Response
    {
        return $this->redirect('toolkit/site-config');
    }

    public function actionScreen(string $section): Response
    {
        $sections = ToolkitSections::all();
        $navItems = ToolkitSections::nav();

        // A group's own URL (toolkit/assets): its first tab
        if (!isset($sections[$section]) && isset(ToolkitSections::GROUPS[$section], $navItems[$section])) {
            return $this->redirect($navItems[$section]['route']);
        }

        if (!isset($sections[$section])) {
            throw new NotFoundHttpException('Toolkit section not found.');
        }

        $nav = [];

        foreach ($navItems as $id => $item) {
            $nav[] = [
                'id' => $id,
                'label' => $item['label'],
                'url' => UrlHelper::cpUrl($item['route']),
                'iconSvg' => ToolkitSections::iconSvg($id),
            ];
        }

        $navId = ToolkitSections::navId($section);

        return $this->asCpScreen()
            ->title('Toolkit')
            ->selectedSubnavItem($navId)
            ->contentHtml(Island::render('screen', [
                'section' => $section,
                'navId' => $navId,
                'title' => $navItems[$navId]['label'],
                'nav' => $nav,
                'tabs' => ToolkitSections::tabs($section),
                'props' => ToolkitSections::props($section),
            ]));
    }
}
