<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use rondodevs\toolkit\fields\StreamingVideoField;
use rondodevs\toolkit\jobs\PrepareHlsJob;
use rondodevs\toolkit\jobs\PreparePosterJob;
use rondodevs\toolkit\streamingvideo\ConversionStatus;
use rondodevs\toolkit\streamingvideo\ConversionStatuses;
use rondodevs\toolkit\streamingvideo\EncodingProfile;
use rondodevs\toolkit\streamingvideo\FFmpeg;
use rondodevs\toolkit\streamingvideo\HlsStorage;
use rondodevs\toolkit\streamingvideo\Poster;
use yii\base\Component;
use yii\db\Schema;

/**
 * HLS streaming for video assets: available when an env var is on, switched
 * on per video with the Streaming Video field, qualities set in the CP,
 * encoded in the queue.
 */
class StreamingVideoService extends Component
{
    // The same variable makes the craft-php template install ffmpeg in the worker
    public const ENV_VAR = 'CRAFT_STREAMING_VIDEO';

    private const SETTINGS_TABLE = '{{%toolkit_streaming_video_settings}}';

    public function isEnabled(): bool
    {
        return (bool)App::parseBooleanEnv('$' . self::ENV_VAR);
    }

    /**
     * @return array{qualities: int[], aboveHd: bool}
     */
    public function getDefaultSettings(): array
    {
        return [
            'qualities' => self::hdQualities(),
            'aboveHd' => true,
        ];
    }

    /**
     * @return array{qualities: int[], aboveHd: bool}
     */
    public function getSettings(): array
    {
        // Not cached: the queue worker is a long-running process
        $stored = $this->ensureSettingsTable()
            ? (new Query())->select(['settings'])->from(self::SETTINGS_TABLE)->scalar()
            : null;

        return $this->normalizeSettings(
            (is_string($stored) ? Json::decodeIfJson($stored) : null) ?: [],
        );
    }

    public function saveSettings(array $settings): void
    {
        if (!$this->ensureSettingsTable()) {
            throw new \RuntimeException('The streaming video settings table could not be created.');
        }

        $db = Craft::$app->getDb();
        $payload = ['settings' => Json::encode($this->normalizeSettings($settings))];

        if ((new Query())->from(self::SETTINGS_TABLE)->exists()) {
            $db->createCommand()->update(self::SETTINGS_TABLE, $payload)->execute();
        } else {
            $db->createCommand()->insert(self::SETTINGS_TABLE, $payload)->execute();
        }
    }

    public function getEncodingProfile(): EncodingProfile
    {
        $settings = $this->getSettings();

        return new EncodingProfile($settings['qualities'], $settings['aboveHd']);
    }

    public function isVideo(Asset $asset): bool
    {
        return str_starts_with((string)$asset->mimeType, 'video/');
    }

    /**
     * The Streaming Video field in the asset's layout, if any.
     */
    public function getField(Asset $asset): ?StreamingVideoField
    {
        foreach ($asset->getFieldLayout()?->getCustomFields() ?? [] as $field) {
            if ($field instanceof StreamingVideoField) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Whether this asset gets HLS streams: streaming available, a video, switched on in its field.
     */
    public function shouldEncode(Asset $asset): bool
    {
        if (!$this->isEnabled() || !$this->isVideo($asset)) {
            return false;
        }

        $field = $this->getField($asset);

        return $field !== null && $asset->getFieldValue($field->handle)['enabled'];
    }

    public function getStatus(Asset $asset): ?ConversionStatus
    {
        return $asset->id ? ConversionStatuses::get($asset->id)['status'] ?? null : null;
    }

    /**
     * Queues the video unless the same file, with the same settings, is
     * already encoded or on its way. Failed encodes are queued again.
     */
    public function queueIfNeeded(Asset $asset): bool
    {
        if (!$asset->id || !$this->shouldEncode($asset)) {
            return false;
        }

        $fingerprint = $this->getEncodingProfile()->fingerprint($asset);
        $current = ConversionStatuses::get($asset->id);

        if ($current && $current['fingerprint'] === $fingerprint && $current['status'] !== ConversionStatus::Failed) {
            return false;
        }

        ConversionStatuses::set($asset->id, ConversionStatus::Pending, $fingerprint);
        Craft::$app->getQueue()->push(new PrepareHlsJob(['assetId' => $asset->id]));

        return true;
    }

    /**
     * Queues every video that needs it: new or replaced files, failed
     * encodes, and videos encoded with other settings.
     *
     * @return int Number of videos queued
     */
    public function queueAll(): int
    {
        $queued = 0;

        // Only the videos switched on queue: queueIfNeeded() checks the field
        foreach (Asset::find()->kind(Asset::KIND_VIDEO)->status(null)->each() as $asset) {
            $queued += (int)$this->queueIfNeeded($asset);
        }

        return $queued;
    }

    /**
     * Absolute URL of the HLS master playlist, or null until the video is
     * encoded (or when streaming is off): the frontend then plays the original file.
     */
    public function getPlaylistUrl(Asset $asset): ?string
    {
        if (!$this->isEnabled() || !$this->isVideo($asset) || $this->getStatus($asset) !== ConversionStatus::Completed) {
            return null;
        }

        return (new HlsStorage($asset))->masterPlaylistUrl();
    }

    /**
     * Queues the video again even if it's up to date, e.g. after a failed
     * upload to the volume or to start over.
     */
    public function reprocess(Asset $asset): void
    {
        ConversionStatuses::set($asset->id, ConversionStatus::Pending, $this->getEncodingProfile()->fingerprint($asset));
        Craft::$app->getQueue()->push(new PrepareHlsJob(['assetId' => $asset->id]));
    }

    /**
     * After a video is saved: queues it when switched on, deletes its streams
     * when switched off, and updates the poster either way.
     */
    public function handleSave(Asset $asset): void
    {
        if (!$asset->id || !$this->isEnabled() || !$this->isVideo($asset)) {
            return;
        }

        $this->applyPosterChange($asset);
        $queued = false;

        if ($this->shouldEncode($asset)) {
            $queued = $this->queueIfNeeded($asset);
        } elseif (ConversionStatuses::get($asset->id)['fingerprint'] ?? null) {
            // Switched off: the frontend plays the original again, the disk space comes back
            (new HlsStorage($asset))->deleteAll();
            ConversionStatuses::clearStreams($asset->id);
        }

        // A new encoding extracts the poster at its end. Otherwise it takes
        // seconds: ahead of the encodings, so thumbnails don't wait hours
        if (!$queued && $this->needsPoster($asset)) {
            Craft::$app->getQueue()->priority(10)->push(new PreparePosterJob(['assetId' => $asset->id]));
        }
    }

    /**
     * The image shown before the video plays: a frame of the video, or an
     * image an editor uploaded or chose instead.
     */
    public function getPoster(Asset $asset): ?Asset
    {
        $posterId = $asset->id && $this->isVideo($asset) ? ConversionStatuses::get($asset->id)['posterAssetId'] ?? null : null;

        return $posterId ? Asset::find()->id($posterId)->status(null)->one() : null;
    }

    /**
     * Whether the poster is an image an editor uploaded or chose, not a frame.
     */
    public function isCustomPoster(Asset $asset): bool
    {
        return $asset->id && (ConversionStatuses::get($asset->id)['posterSource'] ?? null) === Poster::CUSTOM;
    }

    /**
     * Whether a frame should be extracted, HLS on or off: no poster yet, a
     * frame of an older file, or another frame picked in the field. A custom
     * image stays until a frame is picked again.
     */
    public function needsPoster(Asset $asset): bool
    {
        if (!$asset->id || !$this->isEnabled() || !$this->isVideo($asset)) {
            return false;
        }

        if (!$this->getPoster($asset)) {
            return true;
        }

        if ($this->isCustomPoster($asset)) {
            return $this->getPickedTime($asset) !== null;
        }

        return ConversionStatuses::get($asset->id)['posterSource'] !== $this->posterSource($asset);
    }

    /**
     * Extracts the poster frame if needsPoster(). Needs FFmpeg: queue jobs only.
     *
     * @param string|null $inputPath Local copy of the video, downloaded here when null
     */
    public function ensurePoster(Asset $asset, ?string $inputPath = null): void
    {
        if (!$this->needsPoster($asset)) {
            return;
        }

        $workDir = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . uniqid('poster_', true);
        FileHelper::createDirectory($workDir, 0700);
        $copy = $inputPath === null ? $asset->getCopyOfFile() : null;

        try {
            $path = $inputPath ?? $copy;
            $format = FFmpeg::detectFormat($path);

            // Once per file: the suggested moments, and the default frame is the best one
            if ($this->getCandidates($asset) === null) {
                ConversionStatuses::setCandidates($asset->id, Poster::candidates($path, $format), Poster::sourceSignature($asset));
            }

            // The previous frame's asset gets the new file; a custom image is left alone
            $previous = $this->isCustomPoster($asset) ? null : $this->getPoster($asset);
            $time = $this->getPosterTime($asset);

            $poster = Poster::extract($asset, $previous, $path, $format, $time, $workDir);
            ConversionStatuses::setPoster($asset->id, time: $time, posterAssetId: $poster->id, source: $this->posterSource($asset));
            Craft::$app->getElements()->invalidateCachesForElement($asset);
        } finally {
            FileHelper::removeDirectory($workDir);

            if ($copy !== null) {
                @unlink($copy);
            }
        }
    }

    /**
     * Uses an image as the poster instead of a frame, until a frame is picked again.
     */
    private function setCustomPoster(Asset $asset, Asset $image): void
    {
        // The extracted frame is the Toolkit's own: nothing else uses it
        if (!$this->isCustomPoster($asset) && ($frame = $this->getPoster($asset)) && $frame->id !== $image->id) {
            Craft::$app->getElements()->deleteElement($frame, true);
        }

        ConversionStatuses::setCustomPoster($asset->id, $image->id);

        // Forget the picked frame, or the next save would extract it over the image
        $field = $this->getField($asset);

        if ($field && $this->getPickedTime($asset) !== null) {
            $asset->setFieldValue($field->handle, ['posterTime' => null] + $asset->getFieldValue($field->handle));
            Craft::$app->getElements()->saveElement($asset, false);
        } else {
            Craft::$app->getElements()->invalidateCachesForElement($asset);
        }
    }

    /**
     * Applies the poster change posted with the form: an image chosen or
     * uploaded, or back to the best suggested moment. A picked frame needs
     * nothing here: it's the field's posterTime, and needsPoster() sees it.
     */
    private function applyPosterChange(Asset $asset): void
    {
        $field = $this->getField($asset);
        $value = $field ? $asset->getFieldValue($field->handle) : null;

        if (!$value || (!$value['posterImageId'] && !$value['resetPoster'])) {
            return;
        }

        // Only once: the value stays on the element for the rest of the request
        $asset->setFieldValue($field->handle, ['posterImageId' => null, 'resetPoster' => false] + $value);

        if ($value['posterImageId']) {
            $image = Asset::find()->id($value['posterImageId'])->kind(Asset::KIND_IMAGE)->status(null)->one();

            if ($image) {
                $this->setCustomPoster($asset, $image);
            }

            return;
        }

        // A custom image stays an asset of its own: the frame goes in a new one
        if ($this->isCustomPoster($asset)) {
            ConversionStatuses::clearPoster($asset->id);
        }
    }

    /**
     * Saves an uploaded image next to the posters: it becomes the poster when
     * the video is saved with it.
     */
    public function uploadPosterImage(Asset $asset, string $path, string $extension): Asset
    {
        return Poster::save($asset, null, $path, $extension);
    }

    /**
     * The frame picked in the field, in seconds, or null if none was picked.
     */
    private function getPickedTime(Asset $asset): ?float
    {
        $field = $this->getField($asset);

        return $field ? $asset->getFieldValue($field->handle)['posterTime'] : null;
    }

    /**
     * Seconds into the video of the poster frame: the one picked in the field,
     * or the best suggested moment.
     */
    public function getPosterTime(Asset $asset): float
    {
        return $this->getPickedTime($asset)
            ?? $this->getCandidates($asset)[0]
            ?? Poster::DEFAULT_TIME;
    }

    /**
     * Suggested poster moments in seconds, best first, or null until the
     * current file is analysed (with its first poster).
     *
     * @return float[]|null
     */
    public function getCandidates(Asset $asset): ?array
    {
        $candidates = $asset->id ? ConversionStatuses::get($asset->id)['posterCandidates'] ?? null : null;

        return $candidates && $candidates['source'] === Poster::sourceSignature($asset) ? $candidates['times'] : null;
    }

    /**
     * The file and the frame a poster was extracted from: a frame is extracted again when it changes.
     */
    private function posterSource(Asset $asset): string
    {
        return md5(Poster::sourceSignature($asset) . '|' . $this->getPosterTime($asset));
    }

    public function deleteHls(Asset $asset): void
    {
        // Not gated on the status row: on a hard delete the foreign key has
        // already removed it
        if (!$asset->id || !$asset->uid || !$this->isVideo($asset)) {
            return;
        }

        (new HlsStorage($asset))->deleteAll();

        // Only a frame the Toolkit extracted: a custom image may be used elsewhere
        if (!$this->isCustomPoster($asset) && $poster = $this->getPoster($asset)) {
            Craft::$app->getElements()->deleteElement($poster, true);
        }

        ConversionStatuses::delete($asset->id);
    }

    /**
     * @return array<string, int> Number of videos, by status value, every status included
     */
    public function getStatusCounts(): array
    {
        $counts = ConversionStatuses::counts();
        $all = [];

        foreach (ConversionStatus::cases() as $status) {
            $all[$status->value] = $counts[$status->value] ?? 0;
        }

        return $all;
    }

    /**
     * @return array<int, array{asset: ?Asset, assetId: int, error: ?string, dateUpdated: string}>
     */
    public function getRecentFailures(int $limit = 20): array
    {
        $rows = ConversionStatuses::failed($limit);
        $assets = Asset::find()->id(array_column($rows, 'assetId'))->status(null)->indexBy('id')->all();

        return array_map(static fn(array $row): array => $row + ['asset' => $assets[$row['assetId']] ?? null], $rows);
    }

    private function normalizeSettings(array $settings): array
    {
        $defaults = $this->getDefaultSettings();

        $qualities = array_key_exists('qualities', $settings) ? (array)$settings['qualities'] : $defaults['qualities'];
        $qualities = array_values(array_intersect(self::hdQualities(), array_map('intval', $qualities)));

        return [
            'qualities' => $qualities,
            // Saved as includeSource before 1440p and 4K had their own bitrates
            'aboveHd' => (bool)($settings['aboveHd'] ?? $settings['includeSource'] ?? $defaults['aboveHd']),
        ];
    }

    /**
     * @return int[] Heights picked one by one in the settings, 1080p and below
     */
    public static function hdQualities(): array
    {
        return array_values(array_filter(array_keys(EncodingProfile::QUALITIES), static fn(int $height) => $height <= EncodingProfile::HD_HEIGHT));
    }

    private function ensureSettingsTable(): bool
    {
        $db = Craft::$app->getDb();

        if ($db->tableExists(self::SETTINGS_TABLE)) {
            return true;
        }

        try {
            $db->createCommand()->createTable(self::SETTINGS_TABLE, [
                'id' => Schema::TYPE_PK,
                'settings' => Schema::TYPE_TEXT,
                'dateCreated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'dateUpdated' => Schema::TYPE_DATETIME . ' NOT NULL',
                'uid' => Schema::TYPE_CHAR . '(36) DEFAULT NULL',
            ])->execute();

            return true;
        } catch (\Throwable $e) {
            Craft::warning('Toolkit streaming video settings table could not be created automatically: ' . $e->getMessage(), __METHOD__);

            return false;
        }
    }
}
