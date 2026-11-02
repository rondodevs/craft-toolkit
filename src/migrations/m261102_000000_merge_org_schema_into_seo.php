<?php

namespace rondodevs\toolkit\migrations;

use Craft;
use craft\db\Migration;
use rondodevs\toolkit\Toolkit;

/**
 * Merges the deprecated Org Schema field into the SEO field of each entry
 * type (SeoService::mergeOrgSchema()). Plugin migrations run before the
 * project config is applied, so the content is moved while the field still
 * exists; the project config is only changed where it doesn't have the
 * change yet, the environment that makes it.
 */
class m261102_000000_merge_org_schema_into_seo extends Migration
{
    public function safeUp(): bool
    {
        $schemaVersion = Craft::$app->getProjectConfig()->get('plugins.toolkit.schemaVersion', true);
        $updateConfig = version_compare((string)$schemaVersion, '1.1.0', '<');

        $result = Toolkit::getInstance()->seo->mergeOrgSchema($updateConfig);

        echo "    > Org Schema merged into SEO for {$result['elements']} element(s)\n";

        if ($result['kept'] !== []) {
            echo '    > Org Schema kept on ' . implode(', ', $result['kept']) . ": no SEO field in their layout\n";
        }

        return true;
    }

    public function safeDown(): bool
    {
        echo "m261102_000000_merge_org_schema_into_seo cannot be reverted.\n";

        return false;
    }
}
