<?php

namespace rondodevs\toolkit\console\controllers;

use craft\console\Controller;
use craft\helpers\Console;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\console\ExitCode;

class SeoController extends Controller
{
    /**
     * @var bool Whether to leave Ether SEO installed after converting its fields.
     */
    public bool $keepEther = false;

    public function options($actionID): array
    {
        return array_merge(parent::options($actionID), ['keepEther']);
    }

    /**
     * Converts the Ether SEO fields to Toolkit SEO fields in place and uninstalls Ether SEO.
     *
     * ddev craft toolkit/seo/migrate-ether
     */
    public function actionMigrateEther(): int
    {
        $seo = Toolkit::getInstance()->seo;
        $fields = $seo->getEtherFields();

        if ($fields === [] && !$seo->isEtherInstalled()) {
            $this->stdout('Nothing to migrate: no Ether SEO field and Ether SEO is not installed.' . PHP_EOL);
            return ExitCode::OK;
        }

        if ($redirects = $seo->getEtherRedirectCount()) {
            $this->stdout("Ether SEO has $redirects redirect(s): they are not migrated and go with its tables." . PHP_EOL, Console::FG_YELLOW);
        }

        try {
            $result = $seo->migrateFromEther(!$this->keepEther);
        } catch (Throwable $e) {
            $this->stderr($e->getMessage() . PHP_EOL, Console::FG_RED);
            return ExitCode::UNSPECIFIED_ERROR;
        }

        foreach ($result['migrated'] as $handle) {
            $this->stdout("Converted \"$handle\" to Toolkit SEO." . PHP_EOL, Console::FG_GREEN);
        }

        if ($result['uninstalled']) {
            $this->stdout('Uninstalled Ether SEO.' . PHP_EOL, Console::FG_GREEN);
        }

        if ($seo->isEtherInComposer()) {
            $this->stdout('Now remove it from Composer: composer remove ether/seo' . PHP_EOL);
        }

        return ExitCode::OK;
    }
}
