<?php

namespace rondodevs\toolkit\streamingvideo;

/**
 * One HLS quality: a playlist plus its segments, e.g. "720p.m3u8" and
 * "720p_<tag>_000.ts".
 */
final readonly class Variant
{
    public function __construct(
        public string $name,
        public int $maxHeight,
        public int $videoBitrate, // kbps
        public int $audioBitrate, // kbps
    ) {
    }

    public function playlistName(): string
    {
        return "{$this->name}.m3u8";
    }

    /**
     * ffmpeg pattern for the segment files. The tag changes with every
     * encoding: browsers and CDNs cache segments for days, so a re-encoded
     * video can't reuse old names.
     */
    public function segmentPattern(string $tag): string
    {
        return "{$this->name}_{$tag}_%03d.ts";
    }

    /**
     * Peak video bitrate in kbps: no spikes that stall viewers on slower connections.
     */
    public function maxRate(): int
    {
        return (int)round($this->videoBitrate * EncodingProfile::MAXRATE_FACTOR);
    }

    /**
     * Rate control buffer in kbps.
     */
    public function bufSize(): int
    {
        return (int)round($this->videoBitrate * EncodingProfile::BUFSIZE_FACTOR);
    }

    /**
     * Peak bandwidth in bit/s (video maxrate + audio), as the HLS spec expects
     * in the master playlist.
     */
    public function bandwidth(): int
    {
        return ($this->maxRate() + $this->audioBitrate) * 1000;
    }
}
