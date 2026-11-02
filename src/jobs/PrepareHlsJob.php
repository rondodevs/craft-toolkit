<?php

namespace rondodevs\toolkit\jobs;

use Craft;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use craft\queue\BaseJob;
use craft\queue\Queue;
use Exception;
use rondodevs\toolkit\streamingvideo\ConversionStatus;
use rondodevs\toolkit\streamingvideo\ConversionStatuses;
use rondodevs\toolkit\streamingvideo\EncodingProfile;
use rondodevs\toolkit\streamingvideo\EncodingStopped;
use rondodevs\toolkit\streamingvideo\FFmpeg;
use rondodevs\toolkit\streamingvideo\HlsStorage;
use rondodevs\toolkit\streamingvideo\MasterPlaylist;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\queue\RetryableJobInterface;

/**
 * Encodes one video asset to a progressive MP4, always, then to HLS when
 * switched on, one quality at a time, uploading each as soon as it's ready.
 */
class PrepareHlsJob extends BaseJob implements RetryableJobInterface
{
    // Encoding long videos easily exceeds Craft's default 300s TTR, after which
    // the job would be handed to another worker while still running
    private const TTR = 4 * 3600;

    public int $assetId;

    // Encode the MP4 again even if it's up to date (Re-encode)
    public bool $force = false;

    // Leave the HLS streams as they are, only the MP4 (Re-encode MP4)
    public bool $skipHls = false;

    public function execute($queue): void
    {
        $service = Toolkit::getInstance()->streamingVideo;
        $asset = Asset::find()->id($this->assetId)->one();

        // Deleted, moved to another volume or streaming switched off since it was queued
        if (!$asset || !$service->shouldEncode($asset)) {
            return;
        }

        $inputPath = null;
        $workDir = null;

        try {
            if (!FFmpeg::isAvailable()) {
                throw new Exception('FFmpeg/FFprobe not found where the queue worker runs: on craft-php, set ' . $service::ENV_VAR . '=true and redeploy');
            }

            $profile = $service->getEncodingProfile();
            $hls = $service->isHlsEnabled($asset);
            $fingerprint = $profile->fingerprint($asset, $hls);
            ConversionStatuses::set($this->assetId, ConversionStatus::Processing);

            $inputPath = $asset->getCopyOfFile();
            $workDir = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . uniqid('hls_', true);
            FileHelper::createDirectory($workDir, 0700);

            // First encoding or a replaced file: the poster frame, in seconds,
            // so the thumbnail doesn't wait for the encodings
            try {
                $service->ensurePoster($asset, $inputPath);
            } catch (Throwable $e) {
                // The video works without it: the CP shows the generic icon, the frontend no poster
                Craft::warning("Poster extraction failed for asset {$this->assetId}: " . $e->getMessage(), __METHOD__);
            }

            $this->encode($queue, $asset, new HlsStorage($asset), $profile, $inputPath, $workDir, $fingerprint, $hls);

            ConversionStatuses::set($this->assetId, ConversionStatus::Completed, $fingerprint);
            // mp4ProgressiveUrl or hlsPlaylistUrl just changed: drop cached GraphQL responses
            // that include this asset, nothing else would invalidate them
            Craft::$app->getElements()->invalidateCachesForElement($asset);
        } catch (EncodingStopped $e) {
            // Whatever was uploaded before stays live: Re-encode starts over
            ConversionStatuses::set($this->assetId, ConversionStatus::Failed, error: $e->getMessage());
        } catch (Throwable $e) {
            Craft::error("Video encoding failed for asset {$this->assetId}: " . $e->getMessage(), __METHOD__);
            ConversionStatuses::set($this->assetId, ConversionStatus::Failed, error: $e->getMessage());

            throw $e;
        } finally {
            if ($workDir !== null && is_dir($workDir)) {
                FileHelper::removeDirectory($workDir);
            }

            if ($inputPath !== null && file_exists($inputPath)) {
                @unlink($inputPath);
            }
        }
    }

    public function getTtr(): int
    {
        return self::TTR;
    }

    public function canRetry($attempt, $error): bool
    {
        // A failed encode is not retried automatically: "Queue videos" or a resave tries again
        return false;
    }

    protected function defaultDescription(): ?string
    {
        return 'Encoding video';
    }

    /**
     * Whether the job was released from the queue (Release in Queue Manager,
     * or Release all): the row is gone, but nothing stops the PHP process
     * running it, so it has to stop on its own.
     */
    private function wasReleased($queue): bool
    {
        // getJobId() is Craft 5.2+; other queue drivers can't tell
        if (!$queue instanceof Queue || !method_exists($queue, 'getJobId')) {
            return false;
        }

        try {
            return $queue->status($queue->getJobId()) === Queue::STATUS_DONE;
        } catch (Throwable) {
            return false;
        }
    }

    private function encode($queue, Asset $asset, HlsStorage $storage, EncodingProfile $profile, string $inputPath, string $workDir, string $fingerprint, bool $hls): void
    {
        $format = FFmpeg::detectFormat($inputPath);
        [, $sourceHeight] = FFmpeg::sourceResolution($inputPath, $format)
            ?? throw new Exception('Failed to detect the video resolution');

        $variants = $profile->variantsFor($sourceHeight);
        $shouldStop = fn() => $this->wasReleased($queue);
        $encodeHls = $hls && !$this->skipHls;
        // The MP4, then each quality and the master playlist
        $steps = 1 + ($encodeHls ? count($variants) + 1 : 0);

        // Its own tag: switching HLS on or off doesn't encode it again
        $progressiveFile = HlsStorage::progressiveFilename(substr($profile->progressiveFingerprint($asset), 0, 8));

        if ($this->force || (ConversionStatuses::get($this->assetId)['progressiveFile'] ?? null) !== $progressiveFile) {
            $progressive = $profile->progressiveVariant($variants);
            $this->setProgress($queue, 0, "Encoding the {$progressive->name} MP4");
            $progressivePath = $workDir . DIRECTORY_SEPARATOR . $progressiveFile;
            FFmpeg::encodeProgressive($inputPath, $format, $progressive, $progressivePath, $shouldStop);
            $storage->upload([$progressivePath]);
            ConversionStatuses::setProgressiveFile($this->assetId, $progressiveFile);
        }

        if (!$hls) {
            $storage->deleteStaleFiles(null, [], $progressiveFile);

            return;
        }

        $segmentTag = substr($fingerprint, 0, 8);
        $playlists = [
            ...array_map(static fn($variant) => $variant->playlistName(), $variants),
            MasterPlaylist::FILENAME,
        ];

        // The streams already live keep their segments and playlists
        if (!$encodeHls) {
            $storage->deleteStaleFiles($segmentTag, $playlists, $progressiveFile);

            return;
        }

        $resolutions = [];

        foreach ($variants as $i => $variant) {
            $this->setProgress($queue, ($i + 1) / $steps, "Encoding {$variant->name}");

            $playlistPath = $workDir . DIRECTORY_SEPARATOR . $variant->playlistName();
            FFmpeg::encodeVariant($inputPath, $format, $variant, $workDir . DIRECTORY_SEPARATOR . $variant->segmentPattern($segmentTag), $playlistPath, $shouldStop);
            $resolutions[$variant->name] = FFmpeg::outputResolution($playlistPath);

            // Segments first: a playlist must never point to segments not uploaded yet
            $segments = glob($workDir . DIRECTORY_SEPARATOR . "{$variant->name}_{$segmentTag}_*.ts") ?: [];
            $storage->upload([...$segments, $playlistPath]);
        }

        // Last, so players only find the video once every quality is uploaded
        $masterPath = $workDir . DIRECTORY_SEPARATOR . MasterPlaylist::FILENAME;
        file_put_contents($masterPath, MasterPlaylist::render($variants, $resolutions));
        $storage->upload([$masterPath]);
        $this->setProgress($queue, 1, 'Master playlist uploaded');

        $storage->deleteStaleFiles($segmentTag, $playlists, $progressiveFile);
    }
}
