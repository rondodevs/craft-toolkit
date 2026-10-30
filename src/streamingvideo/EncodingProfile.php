<?php

namespace rondodevs\toolkit\streamingvideo;

use craft\elements\Asset;

/**
 * Every encoding setting in one place: the fixed ladder below, plus the
 * qualities picked on the Streaming Video settings tab.
 */
final readonly class EncodingProfile
{
    // Bump when anything below changes: "Queue videos" (or `craft resave/assets`)
    // then re-encodes every video converted with the previous settings
    public const VERSION = 3;

    // Height => [video, audio] bitrate in kbps
    public const QUALITIES = [
        2160 => [16000, 192],
        1440 => [9000, 192],
        1080 => [5000, 128],
        720 => [3000, 128],
        480 => [1500, 96],
        240 => [800, 64],
        144 => [400, 64],
    ];

    // Qualities up to here are picked one by one in the settings; the ones
    // above come with the "Above 1080p" switch, up to the source height
    public const HD_HEIGHT = 1080;

    public const SEGMENT_DURATION = 6;

    // Peak bitrate and rate control buffer, relative to the target bitrate
    public const MAXRATE_FACTOR = 1.07;
    public const BUFSIZE_FACTOR = 1.5;

    /**
     * @param int[] $heights Keys of QUALITIES up to HD_HEIGHT to encode
     * @param bool $aboveHd Also encode the qualities above HD_HEIGHT, up to the source height
     */
    public function __construct(
        public array $heights,
        public bool $aboveHd,
    ) {
    }

    /**
     * Qualities to encode for a source of this height: never upscaled. Above
     * 1080p a 1440p source gets 1440p, a 4K source 1440p and 2160p.
     *
     * @return Variant[]
     */
    public function variantsFor(int $sourceHeight): array
    {
        $variants = [];

        foreach (self::QUALITIES as $height => [$video, $audio]) {
            $selected = $height > self::HD_HEIGHT ? $this->aboveHd : in_array($height, $this->heights, true);

            if ($selected && $sourceHeight >= $height) {
                $variants[] = new Variant("{$height}p", $height, $video, $audio);
            }
        }

        // Source smaller than every selected quality: one stream at its own
        // size, with the bitrate of the nearest quality above it
        if ($variants === []) {
            foreach (array_reverse(self::QUALITIES, true) as $height => [$video, $audio]) {
                if ($height >= $sourceHeight) {
                    return [new Variant('source', $sourceHeight, $video, $audio)];
                }
            }

            return [new Variant('source', $sourceHeight, ...self::QUALITIES[max(array_keys(self::QUALITIES))])];
        }

        return $variants;
    }

    /**
     * Identifies the file, where its HLS files go and the settings they are
     * encoded with: a save that changes none of these doesn't re-encode the video.
     */
    public function fingerprint(Asset $asset): string
    {
        $heights = $this->heights;
        sort($heights);

        return md5(implode('|', [
            $asset->size,
            $asset->dateModified?->getTimestamp(),
            $asset->volumeId . ':' . $asset->getVolume()->getFsHandle(),
            'v' . self::VERSION,
            implode(',', $heights) . ($this->aboveHd ? '+hd' : ''),
        ]));
    }
}
