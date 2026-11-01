<?php

namespace rondodevs\toolkit\controllers;

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

        if (!isset($sections[$section])) {
            throw new NotFoundHttpException('Toolkit section not found.');
        }

        $nav = [];

        foreach ($sections as $id => $item) {
            $nav[] = [
                'id' => $id,
                'label' => $item['label'],
                'url' => ToolkitSections::url($id),
                'iconSvg' => ToolkitSections::iconSvg($id),
            ];
        }

        return $this->asCpScreen()
            ->title('Toolkit')
            ->selectedSubnavItem($section)
            ->contentHtml(Island::render('screen', [
                'section' => $section,
                'title' => $sections[$section]['label'],
                'nav' => $nav,
                'props' => ToolkitSections::props($section),
            ]));
    }
}
