<?php

namespace rondodevs\toolkit\streamingvideo;

/**
 * The master.m3u8 that lists every quality, so the player can pick one.
 */
final class MasterPlaylist
{
    public const FILENAME = 'master.m3u8';

    /**
     * @param Variant[] $variants
     * @param array<string, array{int, int}|null> $resolutions Measured output size, by variant name
     */
    public static function render(array $variants, array $resolutions): string
    {
        $playlist = "#EXTM3U\n#EXT-X-INDEPENDENT-SEGMENTS\n\n";

        foreach ($variants as $variant) {
            $playlist .= '#EXT-X-STREAM-INF:BANDWIDTH=' . $variant->bandwidth();

            if ($resolution = $resolutions[$variant->name] ?? null) {
                $playlist .= ',RESOLUTION=' . implode('x', $resolution);
            }

            $playlist .= "\n" . $variant->playlistName() . "\n\n";
        }

        return $playlist;
    }
}
