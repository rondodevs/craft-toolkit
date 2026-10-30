<?php

namespace rondodevs\toolkit\streamingvideo;

use Craft;
use craft\elements\Asset;
use craft\helpers\Assets as AssetsHelper;
use Exception;

/**
 * The video's poster, an image asset in `video-posters/` of its volume: in
 * GraphQL it's an asset like any other (`videoPoster { ...Media }`), with
 * transforms, focal point and alt text.
 */
final class Poster
{
    // Seconds into the video, when no scene change is found
    public const DEFAULT_TIME = 1.0;

    // Poster source of an image uploaded or chosen instead of a frame
    public const CUSTOM = 'custom';

    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'avif'];

    // Moments suggested in the Streaming Video field
    public const CANDIDATES = 3;

    private const WIDTH = 1920;
    private const FOLDER = 'video-posters';

    /**
     * Changes when the video file is replaced.
     */
    public static function sourceSignature(Asset $video): string
    {
        return md5($video->size . '|' . $video->dateModified?->getTimestamp());
    }

    /**
     * The moments to suggest: the strongest scene changes, then a quarter,
     * half and three quarters of the way through.
     *
     * @return float[] Seconds, best first
     */
    public static function candidates(string $inputPath, string $format): array
    {
        $duration = FFmpeg::duration($inputPath, $format);

        if (!$duration) {
            return [self::DEFAULT_TIME];
        }

        $times = FFmpeg::sceneChanges($inputPath, $format, $duration, self::CANDIDATES);

        foreach ([0.25, 0.5, 0.75] as $fraction) {
            if (count($times) >= self::CANDIDATES) {
                break;
            }

            $time = round($duration * $fraction, 1);

            if (!array_filter($times, static fn($other) => abs($other - $time) < $duration / 10)) {
                $times[] = $time;
            }
        }

        return array_map(static fn($time) => round($time, 1), $times);
    }

    /**
     * Extracts the frame at $time (moved inside the video if it's past the
     * end) and saves it as the video's poster.
     */
    public static function extract(Asset $video, ?Asset $current, string $inputPath, string $format, float $time, string $workDir): Asset
    {
        $duration = FFmpeg::duration($inputPath, $format);

        if ($duration !== null) {
            $time = min($time, max(0, $duration - 1));
        }

        $path = $workDir . DIRECTORY_SEPARATOR . 'poster.jpg';
        FFmpeg::extractFrame($inputPath, $format, $time, $path, self::WIDTH);

        return self::save($video, $current, $path, 'jpg');
    }

    /**
     * Saves the image as a new asset, or as the new file of $current.
     */
    public static function save(Asset $video, ?Asset $current, string $path, string $extension): Asset
    {
        // A new name every time, so browsers and CDNs never show an old poster
        $tag = substr(md5(uniqid('', true)), 0, 8);
        $filename = AssetsHelper::prepareAssetName(pathinfo($video->getFilename(), PATHINFO_FILENAME) . "-poster-{$tag}.{$extension}");

        if ($current) {
            Craft::$app->getAssets()->replaceAssetFile($current, $path, $filename);

            return $current;
        }

        $poster = new Asset();
        $poster->tempFilePath = $path;
        $poster->setFilename($filename);
        $poster->newFolderId = Craft::$app->getAssets()->ensureFolderByFullPathAndVolume(self::FOLDER, $video->getVolume(), false)->id;
        $poster->setVolumeId($video->volumeId);
        $poster->title = $video->title . ' (poster)';
        $poster->avoidFilenameConflicts = true;
        $poster->setScenario(Asset::SCENARIO_CREATE);

        if (!Craft::$app->getElements()->saveElement($poster)) {
            throw new Exception('Could not save the poster asset: ' . implode(' ', $poster->getFirstErrors()));
        }

        return $poster;
    }
}
