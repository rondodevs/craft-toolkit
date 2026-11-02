<?php

namespace rondodevs\toolkit\services;

use Craft;
use craft\elements\Asset;
use craft\helpers\App;
use craft\helpers\FileHelper;
use craft\helpers\Json;
use rondodevs\toolkit\jobs\PrepareHlsJob;
use rondodevs\toolkit\jobs\PreparePosterJob;
use rondodevs\toolkit\streamingvideo\ConversionStatus;
use rondodevs\toolkit\streamingvideo\ConversionStatuses;
use rondodevs\toolkit\streamingvideo\EncodingProfile;
use rondodevs\toolkit\streamingvideo\FFmpeg;
use rondodevs\toolkit\streamingvideo\HlsStorage;
use rondodevs\toolkit\streamingvideo\Poster;
use rondodevs\toolkit\support\SettingsTable;
use yii\base\Component;
use yii\db\Schema;

/**
 * A progressive MP4 for every video asset, plus HLS streaming switched on per
 * video in the Streaming block of its edit form: available when an env var is
 * on, qualities set in the CP, encoded in the queue.
 */
class StreamingVideoService extends Component
{
    // The same variable makes the craft-php template install ffmpeg in the worker
    public const ENV_VAR = 'CRAFT_STREAMING_VIDEO';

    private ?SettingsTable $table = null;

    private function table(): SettingsTable
    {
        return $this->table ??= new SettingsTable('{{%toolkit_streaming_video_settings}}', [
            'settings' => Schema::TYPE_TEXT,
        ], 'streaming-video-settings.json');
    }

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
        $stored = $this->table()->first()['settings'] ?? null;

        return $this->normalizeSettings((is_string($stored) ? Json::decodeIfJson($stored) : $stored) ?: []);
    }

    public function saveSettings(array $settings): void
    {
        $this->table()->put(['settings' => Json::encode($this->normalizeSettings($settings))]);
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
     * Whether this asset is encoded: streaming available and a video. Every
     * video gets the MP4, HLS on or off.
     */
    public function shouldEncode(Asset $asset): bool
    {
        return $asset->id && $this->isEnabled() && $this->isVideo($asset);
    }

    /**
     * Whether this video also gets HLS streams: Convert to HLS switched on in its Streaming block.
     */
    public function isHlsEnabled(Asset $asset): bool
    {
        return $this->shouldEncode($asset) && (ConversionStatuses::get($asset->id)['hlsEnabled'] ?? false);
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

        $fingerprint = $this->getEncodingProfile()->fingerprint($asset, $this->isHlsEnabled($asset));
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

        // Every video: the MP4 doesn't depend on the HLS switch
        foreach (Asset::find()->kind(Asset::KIND_VIDEO)->status(null)->each() as $asset) {
            $queued += (int)$this->queueIfNeeded($asset);
        }

        return $queued;
    }

    /**
     * Absolute URL of the HLS master playlist, or null until the video is
     * encoded (or with HLS or streaming off): the frontend then plays the MP4
     * or the original file.
     */
    public function getPlaylistUrl(Asset $asset): ?string
    {
        if (!$this->isHlsEnabled($asset) || $this->getStatus($asset) !== ConversionStatus::Completed) {
            return null;
        }

        return (new HlsStorage($asset))->masterPlaylistUrl();
    }

    /**
     * Absolute URL of the progressive MP4, or null until it's encoded (or
     * when streaming is off). Kept while the video is re-encoded: the file
     * is only replaced once the new one is uploaded.
     */
    public function getMp4ProgressiveUrl(Asset $asset): ?string
    {
        if (!$this->shouldEncode($asset)) {
            return null;
        }

        $filename = ConversionStatuses::get($asset->id)['progressiveFile'] ?? null;

        return $filename ? (new HlsStorage($asset))->url($filename) : null;
    }

    /**
     * Queues the video again even if it's up to date, e.g. after a failed
     * upload to the volume or to start over.
     */
    public function reprocess(Asset $asset): void
    {
        ConversionStatuses::set($asset->id, ConversionStatus::Pending, $this->getEncodingProfile()->fingerprint($asset, $this->isHlsEnabled($asset)));
        Craft::$app->getQueue()->push(new PrepareHlsJob(['assetId' => $asset->id, 'force' => true]));
    }

    /**
     * After a video is saved: queues it when the file, the settings or the
     * HLS switch changed (switched off, the job deletes the streams and keeps
     * the MP4), and updates the poster.
     */
    public function handleSave(Asset $asset): void
    {
        if (!$asset->id || !$this->isEnabled() || !$this->isVideo($asset)) {
            return;
        }

        $this->applyFormValues($asset);
        $queued = $this->queueIfNeeded($asset);

        // A new encoding extracts the poster before encoding. Otherwise it takes
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

        // Also forgets the picked frame, or it would be extracted over the image
        ConversionStatuses::setCustomPoster($asset->id, $image->id);
        Craft::$app->getElements()->invalidateCachesForElement($asset);
    }

    /**
     * Applies what the Streaming block posted with the asset's form: HLS on or
     * off, the picked frame, an image chosen or uploaded, or back to the best
     * suggested moment. Nothing when the asset was saved some other way.
     */
    private function applyFormValues(Asset $asset): void
    {
        $values = $asset->toolkitStreaming ?? null;

        if (!is_array($values)) {
            return;
        }

        // Only once: the values stay on the element for the rest of the request
        $asset->toolkitStreaming = null;

        $time = $values['posterTime'] ?? '';
        ConversionStatuses::setVideoSettings($asset->id, !empty($values['enabled']), is_numeric($time) ? max(0, (float)$time) : null);

        if (!empty($values['posterImageId'])) {
            $image = Asset::find()->id((int)$values['posterImageId'])->kind(Asset::KIND_IMAGE)->status(null)->one();

            if ($image) {
                $this->setCustomPoster($asset, $image);
            }

            return;
        }

        // Back to the best moment: a custom image stays an asset of its own, the frame goes in a new one
        if (!empty($values['resetPoster']) && $this->isCustomPoster($asset)) {
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
     * The frame picked in the Streaming block, in seconds, or null if none was picked.
     */
    public function getPickedTime(Asset $asset): ?float
    {
        return $asset->id ? ConversionStatuses::get($asset->id)['pickedTime'] ?? null : null;
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
}
