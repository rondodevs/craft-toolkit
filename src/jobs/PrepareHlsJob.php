<?php

namespace rondodevs\toolkit\jobs;

use Craft;
use craft\elements\Asset;
use craft\helpers\FileHelper;
use craft\queue\BaseJob;
use Exception;
use rondodevs\toolkit\streamingvideo\ConversionStatus;
use rondodevs\toolkit\streamingvideo\ConversionStatuses;
use rondodevs\toolkit\streamingvideo\EncodingProfile;
use rondodevs\toolkit\streamingvideo\FFmpeg;
use rondodevs\toolkit\streamingvideo\HlsStorage;
use rondodevs\toolkit\streamingvideo\MasterPlaylist;
use rondodevs\toolkit\Toolkit;
use Throwable;
use yii\queue\RetryableJobInterface;

/**
 * Encodes one video asset to HLS, one quality at a time, and uploads each
 * quality as soon as it's ready.
 */
class PrepareHlsJob extends BaseJob implements RetryableJobInterface
{
    // Encoding long videos easily exceeds Craft's default 300s TTR, after which
    // the job would be handed to another worker while still running
    private const TTR = 4 * 3600;

    public int $assetId;

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
            $fingerprint = $profile->fingerprint($asset);
            ConversionStatuses::set($this->assetId, ConversionStatus::Processing);

            $inputPath = $asset->getCopyOfFile();
            $workDir = Craft::$app->getPath()->getTempPath() . DIRECTORY_SEPARATOR . uniqid('hls_', true);
            FileHelper::createDirectory($workDir, 0700);

            $this->encode($queue, new HlsStorage($asset), $profile, $inputPath, $workDir, $fingerprint);

            // First encoding or a replaced file: the poster frame
            try {
                $service->ensurePoster($asset, $inputPath);
            } catch (Throwable $e) {
                // The stream works without it: the CP shows the generic icon, the frontend no poster
                Craft::warning("Poster extraction failed for asset {$this->assetId}: " . $e->getMessage(), __METHOD__);
            }

            ConversionStatuses::set($this->assetId, ConversionStatus::Completed, $fingerprint);
            // hlsPlaylistUrl just changed from null: drop cached GraphQL responses
            // that include this asset, nothing else would invalidate them
            Craft::$app->getElements()->invalidateCachesForElement($asset);
        } catch (Throwable $e) {
            Craft::error("HLS encoding failed for asset {$this->assetId}: " . $e->getMessage(), __METHOD__);
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
        return 'Encoding video for streaming';
    }

    private function encode($queue, HlsStorage $storage, EncodingProfile $profile, string $inputPath, string $workDir, string $fingerprint): void
    {
        $format = FFmpeg::detectFormat($inputPath);
        [, $sourceHeight] = FFmpeg::sourceResolution($inputPath, $format)
            ?? throw new Exception('Failed to detect the video resolution');

        $variants = $profile->variantsFor($sourceHeight);
        $segmentTag = substr($fingerprint, 0, 8);
        $resolutions = [];

        foreach ($variants as $i => $variant) {
            $this->setProgress($queue, $i / (count($variants) + 1), "Encoding {$variant->name}");

            $playlistPath = $workDir . DIRECTORY_SEPARATOR . $variant->playlistName();
            FFmpeg::encodeVariant($inputPath, $format, $variant, $workDir . DIRECTORY_SEPARATOR . $variant->segmentPattern($segmentTag), $playlistPath);
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

        $storage->deleteStaleFiles($segmentTag, [
            ...array_map(static fn($variant) => $variant->playlistName(), $variants),
            MasterPlaylist::FILENAME,
        ]);
    }
}
