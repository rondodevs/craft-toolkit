<?php

namespace rondodevs\toolkit\jobs;

use craft\elements\Asset;
use craft\queue\BaseJob;
use Exception;
use rondodevs\toolkit\streamingvideo\FFmpeg;
use rondodevs\toolkit\Toolkit;

/**
 * Extracts the poster frame, without re-encoding the video.
 * In the queue because FFmpeg is only installed in the worker.
 */
class PreparePosterJob extends BaseJob
{
    public int $assetId;

    public function execute($queue): void
    {
        $service = Toolkit::getInstance()->streamingVideo;
        $asset = Asset::find()->id($this->assetId)->one();

        // HLS on or off: every video gets a frame, for its thumbnail at least
        if (!$asset || !$service->needsPoster($asset)) {
            return;
        }

        if (!FFmpeg::isAvailable()) {
            throw new Exception('FFmpeg/FFprobe not found where the queue worker runs: on craft-php, set ' . $service::ENV_VAR . '=true and redeploy');
        }

        $service->ensurePoster($asset);
    }

    protected function defaultDescription(): ?string
    {
        return 'Extracting video poster frame';
    }
}
