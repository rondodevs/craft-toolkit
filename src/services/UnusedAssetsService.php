<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\helpers\Db;
use DateTime;
use rondodevs\toolkit\streamingvideo\ConversionStatuses;
use rondodevs\toolkit\Toolkit;
use yii\base\Component;

/**
 * Finds the assets of a volume that nothing uses, to move them to the trash.
 * Meant for volumes like `media`, where every file belongs to some content.
 *
 * "Used" is on the safe side: anything that might still need the file keeps it.
 */
class UnusedAssetsService extends Component
{
    // Recent uploads may be about to be attached to an entry
    public const MIN_AGE_HOURS = 24;

    // Rows shown and trashed in one go
    public const LIMIT = 500;

    /**
     * Unused assets of the volume, oldest first.
     *
     * @param int[]|null $onlyIds Restrict to these assets (the ones ticked before trashing)
     * @return Asset[]
     */
    public function find(int $volumeId, ?array $onlyIds = null): array
    {
        return $this->query($volumeId, $onlyIds)->limit(self::LIMIT)->all();
    }

    /**
     * Total number of unused assets in the volume, past LIMIT too.
     */
    public function count(int $volumeId): int
    {
        return (int)$this->query($volumeId)->count();
    }

    /**
     * Moves the ticked assets to the trash, if they are still unused: they can
     * be restored from there until Craft's garbage collection removes them.
     *
     * @param int[] $assetIds
     * @return int Number of assets trashed
     */
    public function trash(int $volumeId, array $assetIds): int
    {
        if ($assetIds === []) {
            return 0;
        }

        $trashed = 0;

        foreach ($this->find($volumeId, $assetIds) as $asset) {
            if (Craft::$app->getElements()->deleteElement($asset)) {
                $trashed++;
            }
        }

        return $trashed;
    }

    /**
     * A sentence per reason an asset counts as used, for the screen.
     *
     * @return string[]
     */
    public static function usedWhen(): array
    {
        return [
            'It is related from any element, drafts, revisions and trashed entries included.',
            'It is cited in rich text or a Link field (<code>{asset:ID}</code> reference tags).',
            "It is a user photo, the Org Schema logo, or a video's Streaming Video poster.",
            'It was uploaded less than ' . self::MIN_AGE_HOURS . ' hours ago.',
        ];
    }

    private function query(int $volumeId, ?array $onlyIds = null): AssetQuery
    {
        $query = Asset::find()
            ->volumeId($volumeId)
            ->status(null)
            ->dateCreated('< ' . Db::prepareDateForDb(new DateTime('-' . self::MIN_AGE_HOURS . ' hours')))
            ->andWhere(['not in', 'elements.id', (new Query())->select(['targetId'])->from(Table::RELATIONS)])
            ->orderBy(['elements.dateCreated' => SORT_ASC]);

        $referenced = $this->referencedIds();

        if ($referenced !== []) {
            $query->andWhere(['not in', 'elements.id', $referenced]);
        }

        if ($onlyIds !== null) {
            $query->id(array_map('intval', $onlyIds) ?: [0]);
        }

        return $query;
    }

    /**
     * Assets used without a relation: reference tags in content, user photos,
     * the Toolkit's own settings.
     *
     * @return int[]
     */
    private function referencedIds(): array
    {
        $ids = [];

        $overrides = Toolkit::getInstance()->orgSchema->getOverrides();

        // Rich text and Link fields store "{asset:12:url}" or "{asset:12@1:url}"
        $contents = (new Query())
            ->select(['content'])
            ->from(Table::ELEMENTS_SITES)
            ->where(['like', 'content', '{asset:'])
            ->column();

        foreach ($contents as $content) {
            preg_match_all('/\{asset:(\d+)/', (string)$content, $matches);
            array_push($ids, ...array_map('intval', $matches[1]));
        }

        array_push($ids, ...array_map('intval', (new Query())
            ->select(['photoId'])
            ->from(Table::USERS)
            ->where(['not', ['photoId' => null]])
            ->column()));

        // Org Schema logos, one per site
        array_walk_recursive($overrides, static function($value, $key) use (&$ids) {
            if ($key === 'logoAssetId' && is_numeric($value)) {
                $ids[] = (int)$value;
            }
        });

        if (Craft::$app->getDb()->tableExists(ConversionStatuses::TABLE)) {
            array_push($ids, ...array_map('intval', (new Query())
                ->select(['posterAssetId'])
                ->from(ConversionStatuses::TABLE)
                ->where(['not', ['posterAssetId' => null]])
                ->column()));
        }

        return array_values(array_unique($ids));
    }
}
