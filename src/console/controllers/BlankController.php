<?php

namespace rondodevs\toolkit\console\controllers;

use Craft;
use craft\console\Controller;

class BlankController extends Controller
{
  /**
   * Handle
   * 
   * ddev craft toolkit/blank/run 
   * 
   * console commands
   *
   * The first line of this method docblock is displayed as the description
   * of the Console Command in ./craft help
   *
   * @return mixed
   */
  public function actionRun()
  {
    // Get all site IDs to handle each language
    $sites = Craft::$app->getSites()->getAllSites();

    foreach ($sites as $site) {
      echo "Processing site: " . $site->name . PHP_EOL;
    }
  }
}
