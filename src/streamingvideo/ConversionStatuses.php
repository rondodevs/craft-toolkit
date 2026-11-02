<?php

namespace rondodevs\toolkit\streamingvideo;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use yii\db\Expression;
use yii\db\Schema;

/**
 * The conversion status of each video, one row per asset. Deleted with the asset.
 */
final class ConversionStatuses
{
    public const TABLE = '{{%toolkit_streaming_video_status}}';

    private static bool $tableReady = false;

    /**
     * @return array{status: ConversionStatus, fingerprint: ?string, error: ?string, posterTime: ?float, posterAssetId: ?int, posterSource: ?string, posterCandidates: ?array, hlsEnabled: bool, pickedTime: ?float, progressiveFile: ?string}|null
     */
    public static function get(int $assetId): ?array
    {
        self::ensureTable();

        $row = (new Query())
            ->select(['status', 'fingerprint', 'error', 'posterTime', 'posterAssetId', 'posterSource', 'posterCandidates', 'hlsEnabled', 'pickedTime', 'progressiveFile'])
            ->from(self::TABLE)
            ->where(['assetId' => $assetId])
            ->one();

        if (!$row) {
            return null;
        }

        return [
            'status' => ConversionStatus::tryFrom($row['status']) ?? ConversionStatus::Pending,
            'fingerprint' => $row['fingerprint'],
            'error' => $row['error'],
            'posterTime' => $row['posterTime'] !== null ? (float)$row['posterTime'] : null,
            'posterAssetId' => $row['posterAssetId'] !== null ? (int)$row['posterAssetId'] : null,
            'posterSource' => $row['posterSource'],
            'posterCandidates' => $row['posterCandidates'] ? json_decode($row['posterCandidates'], true) : null,
            'hlsEnabled' => (bool)$row['hlsEnabled'],
            'pickedTime' => $row['pickedTime'] !== null ? (float)$row['pickedTime'] : null,
            'progressiveFile' => $row['progressiveFile'],
        ];
    }

    /**
     * @param string|null $fingerprint Kept as it is when null
     */
    public static function set(int $assetId, ConversionStatus $status, ?string $fingerprint = null, ?string $error = null): void
    {
        self::ensureTable();

        $columns = [
            'status' => $status->value,
            'error' => $error !== null ? mb_substr($error, 0, 2000) : null,
        ];

        if ($fingerprint !== null) {
            $columns['fingerprint'] = $fingerprint;
        }

        Db::upsert(self::TABLE, ['assetId' => $assetId] + $columns, $columns);
    }

    /**
     * File name of the progressive MP4 of the current encoding, in the asset's HLS directory.
     */
    public static function setProgressiveFile(int $assetId, string $filename): void
    {
        self::ensureTable();

        Db::update(self::TABLE, ['progressiveFile' => $filename], ['assetId' => $assetId]);
    }

    /**
     * Saves the poster frame's time, asset and source (the file and time it
     * was extracted from), leaving the conversion status alone.
     */
    public static function setPoster(int $assetId, ?float $time = null, ?int $posterAssetId = null, ?string $source = null): void
    {
        self::ensureTable();

        $columns = array_filter(['posterTime' => $time, 'posterAssetId' => $posterAssetId, 'posterSource' => $source], static fn($value) => $value !== null);

        if ($columns === []) {
            return;
        }

        Db::upsert(self::TABLE, ['assetId' => $assetId, 'status' => ConversionStatus::Off->value] + $columns, $columns);
    }

    /**
     * What the editor set in the Streaming block of the asset form: HLS on or
     * off, and the frame picked for the poster (null: the best moment).
     */
    public static function setVideoSettings(int $assetId, bool $hlsEnabled, ?float $pickedTime): void
    {
        self::ensureTable();

        $columns = ['hlsEnabled' => $hlsEnabled, 'pickedTime' => $pickedTime];
        Db::upsert(self::TABLE, ['assetId' => $assetId, 'status' => ConversionStatus::Off->value] + $columns, $columns);
    }

    /**
     * An image used as the poster instead of a frame: the last frame time is
     * forgotten, so picking any frame extracts it again.
     */
    public static function setCustomPoster(int $assetId, int $posterAssetId): void
    {
        self::ensureTable();

        $columns = ['posterAssetId' => $posterAssetId, 'posterSource' => Poster::CUSTOM, 'posterTime' => null, 'pickedTime' => null];
        Db::upsert(self::TABLE, ['assetId' => $assetId, 'status' => ConversionStatus::Off->value] + $columns, $columns);
    }

    /**
     * Forgets the poster, leaving its asset alone: the next frame goes in a new one.
     */
    public static function clearPoster(int $assetId): void
    {
        self::ensureTable();

        Db::update(self::TABLE, ['posterAssetId' => null, 'posterSource' => null, 'posterTime' => null], ['assetId' => $assetId]);
    }

    /**
     * @param float[] $times Suggested poster moments
     * @param string $source Signature of the file they were found in
     */
    public static function setCandidates(int $assetId, array $times, string $source): void
    {
        self::ensureTable();

        $columns = ['posterCandidates' => json_encode(['source' => $source, 'times' => $times])];
        Db::upsert(self::TABLE, ['assetId' => $assetId, 'status' => ConversionStatus::Off->value] + $columns, $columns);
    }

    public static function delete(int $assetId): void
    {
        self::ensureTable();

        Db::delete(self::TABLE, ['assetId' => $assetId]);
    }

    /**
     * @return array<string, int> Number of videos, by status value
     */
    public static function counts(): array
    {
        self::ensureTable();

        $counts = (new Query())
            ->select(['COUNT(*)'])
            ->from(self::TABLE)
            ->groupBy('status')
            ->indexBy('status')
            ->column();

        return array_map('intval', $counts);
    }

    /**
     * @return array<int, array{assetId: int, error: ?string, dateUpdated: string}>
     */
    public static function failed(int $limit): array
    {
        self::ensureTable();

        return (new Query())
            ->select(['assetId', 'error', 'dateUpdated'])
            ->from(self::TABLE)
            ->where(['status' => ConversionStatus::Failed->value])
            ->orderBy(['dateUpdated' => SORT_DESC])
            ->limit($limit)
            ->all();
    }

    /**
     * The Toolkit has no migrations: like its other tables, this one is
     * created on first use.
     */
    private static function ensureTable(): void
    {
        if (self::$tableReady) {
            return;
        }

        $db = Craft::$app->getDb();

        if (!$db->tableExists(self::TABLE)) {
            $db->createCommand()->createTable(self::TABLE, [
                'id' => Schema::TYPE_PK,
                'assetId' => Schema::TYPE_INTEGER . ' NOT NULL',
                'status' => Schema::TYPE_STRING . '(16) NOT NULL',
                'fingerprint' => Schema::TYPE_CHAR . '(32) DEFAULT NULL',
                'error' => Schema::TYPE_TEXT,
                'dateCreated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'dateUpdated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'uid' => Schema::TYPE_CHAR . '(36) DEFAULT NULL',
            ])->execute();

            $db->createCommand()->createIndex('idx_toolkit_streaming_video_status_assetId', self::TABLE, 'assetId', true)->execute();
            $db->createCommand()->addForeignKey('fk_toolkit_streaming_video_status_assetId', self::TABLE, 'assetId', '{{%assets}}', 'id', 'CASCADE', 'CASCADE')->execute();
            $db->getSchema()->refreshTableSchema(self::TABLE);
        }

        // Poster, per-video and MP4 columns, added after the first release of the table
        $missing = array_filter([
            'posterTime' => Schema::TYPE_FLOAT . ' DEFAULT NULL',
            'posterAssetId' => Schema::TYPE_INTEGER . ' DEFAULT NULL',
            'posterSource' => Schema::TYPE_STRING . '(32) DEFAULT NULL',
            'posterCandidates' => Schema::TYPE_TEXT,
            'hlsEnabled' => Schema::TYPE_BOOLEAN . ' NOT NULL DEFAULT 0',
            'pickedTime' => Schema::TYPE_FLOAT . ' DEFAULT NULL',
            'progressiveFile' => Schema::TYPE_STRING . '(64) DEFAULT NULL',
        ], static fn($type, $column) => $db->getTableSchema(self::TABLE)?->getColumn($column) === null, ARRAY_FILTER_USE_BOTH);

        foreach ($missing as $column => $type) {
            $db->createCommand()->addColumn(self::TABLE, $column, $type)->execute();
        }

        // Before 0.0.20 these lived in a Streaming Video field: videos already
        // encoded stay on, and the poster keeps its frame
        if (isset($missing['hlsEnabled'])) {
            $db->createCommand()->update(self::TABLE, ['hlsEnabled' => true], ['not', ['fingerprint' => null]])->execute();
            $db->createCommand()->update(self::TABLE, ['pickedTime' => new Expression('[[posterTime]]')], ['and', ['not', ['posterTime' => null]], ['or', ['posterSource' => null], ['not', ['posterSource' => Poster::CUSTOM]]]])->execute();
        }

        if ($missing !== []) {
            $db->getSchema()->refreshTableSchema(self::TABLE);
        }

        self::$tableReady = true;
    }
}
