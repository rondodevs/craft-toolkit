<?php

namespace rondodevs\toolkit\streamingvideo;

use Exception;
use mikehaertl\shellcommand\Command;

/**
 * The ffmpeg/ffprobe calls, with uploaded files read as safely as possible.
 */
final class FFmpeg
{
    // Demuxers ffmpeg may use on uploaded files. Anything else (HLS, concat,
    // AVI...) can make ffmpeg read local files and leak them into the output
    private const ALLOWED_FORMATS = ['mov', 'mp4', 'm4a', '3gp', '3g2', 'mj2', 'matroska', 'webm', 'mpegts'];

    public static function isAvailable(): bool
    {
        exec('ffmpeg -version 2>&1', $output, $ffmpegExitCode);
        exec('ffprobe -version 2>&1', $output, $ffprobeExitCode);

        return $ffmpegExitCode === 0 && $ffprobeExitCode === 0;
    }

    /**
     * Returns the demuxer to read the upload with, refusing anything outside ALLOWED_FORMATS.
     */
    public static function detectFormat(string $path): string
    {
        exec('ffprobe -v error' . self::restrictedInput() . ' -show_entries format=format_name -of default=nw=1:nk=1 ' . escapeshellarg($path), $output, $exitCode);

        // e.g. "mov,mp4,m4a,3gp,3g2,mj2" -> "mov"
        $format = explode(',', $output[0] ?? '')[0];

        if ($exitCode !== 0 || !in_array($format, self::ALLOWED_FORMATS, true)) {
            throw new Exception('Unsupported or unsafe video container: convert the file to MP4');
        }

        return $format;
    }

    /**
     * @return array{int, int}|null Width and height of the upload's video stream
     */
    public static function sourceResolution(string $path, string $format): ?array
    {
        return self::probeResolution(self::restrictedInput($format), $path);
    }

    /**
     * @return array{int, int}|null Width and height of one of our own HLS playlists
     */
    public static function outputResolution(string $playlistPath): ?array
    {
        return self::probeResolution(' -protocol_whitelist file -format_whitelist hls,mpegts', $playlistPath);
    }

    /**
     * @return float|null Length of the upload in seconds
     */
    public static function duration(string $path, string $format): ?float
    {
        exec('ffprobe -v error' . self::restrictedInput($format) . ' -show_entries format=duration -of default=nw=1:nk=1 ' . escapeshellarg($path), $output, $exitCode);

        return $exitCode === 0 && is_numeric($output[0] ?? null) ? (float)$output[0] : null;
    }

    /**
     * Times of the strongest scene changes, best first, at least a tenth of
     * the video apart. Only keyframes are decoded: fast even on long videos.
     *
     * @return float[] Seconds, at most $count
     */
    public static function sceneChanges(string $path, string $format, float $duration, int $count): array
    {
        $command = new Command('ffmpeg -hide_banner -skip_frame nokey' . self::restrictedInput($format) . ' -i ' . escapeshellarg($path)
            . ' -vf ' . escapeshellarg("scale=160:-2,select='gt(scene,0.15)',metadata=print:file=-")
            . ' -an -f null -');

        if (!$command->execute()) {
            return [];
        }

        // "frame:0 pts:17408 pts_time:1.41" then "lavfi.scene_score=0.50"
        preg_match_all('/pts_time:([\d.]+)\s+lavfi\.scene_score=([\d.]+)/', $command->getOutput(), $matches, PREG_SET_ORDER);
        $scenes = array_map(static fn($match) => ['time' => (float)$match[1], 'score' => (float)$match[2]], $matches);
        usort($scenes, static fn($a, $b) => $b['score'] <=> $a['score']);

        $picked = [];

        foreach ($scenes as $scene) {
            // Half a second into the new scene: its first frame is often a transition
            $time = min($scene['time'] + 0.5, max(0, $duration - 1));

            foreach ($picked as $other) {
                if (abs($other - $time) < $duration / 10) {
                    continue 2;
                }
            }

            $picked[] = $time;

            if (count($picked) === $count) {
                break;
            }
        }

        return $picked;
    }

    /**
     * Saves the frame at $time as a JPEG, no wider than $maxWidth.
     */
    public static function extractFrame(string $inputPath, string $format, float $time, string $outputPath, int $maxWidth): void
    {
        // -ss before -i seeks on keyframes first, then decodes to the exact frame
        $command = new Command('ffmpeg -y' . self::restrictedInput($format) . ' -ss ' . sprintf('%.3F', $time) . ' -i ' . escapeshellarg($inputPath)
            . ' -frames:v 1 -update 1 -q:v 3'
            . ' -vf ' . escapeshellarg("scale='min({$maxWidth},iw)':-2")
            . ' ' . escapeshellarg($outputPath));

        if (!$command->execute() || !is_file($outputPath)) {
            throw new Exception('ffmpeg could not extract the poster frame: ' . $command->getError());
        }
    }

    /**
     * @param callable(): bool $shouldStop Asked every second: true kills ffmpeg
     */
    public static function encodeVariant(string $inputPath, string $format, Variant $variant, string $segmentPath, string $playlistPath, callable $shouldStop): void
    {
        $segmentDuration = EncodingProfile::SEGMENT_DURATION;

        self::run('ffmpeg -hide_banner -y' . self::restrictedInput($format) . ' -i ' . escapeshellarg($inputPath)
            . ' -vf ' . escapeshellarg("scale=-2:{$variant->maxHeight}:force_original_aspect_ratio=decrease:force_divisible_by=2")
            . ' -c:v libx264 -b:v ' . $variant->videoBitrate . 'k'
            . ' -maxrate ' . $variant->maxRate() . 'k -bufsize ' . $variant->bufSize() . 'k'
            // A keyframe at every segment boundary, at the same timestamps in
            // every variant: exact segments and seamless quality switches
            . ' -force_key_frames ' . escapeshellarg("expr:gte(t,n_forced*{$segmentDuration})") . ' -sc_threshold 0'
            . ' -c:a aac -b:a ' . $variant->audioBitrate . 'k -ac 2 -ar 48000'
            . ' -hls_time ' . $segmentDuration . ' -hls_playlist_type vod -hls_flags independent_segments'
            . ' -hls_segment_filename ' . escapeshellarg($segmentPath)
            . ' ' . escapeshellarg($playlistPath), $shouldStop, 'ffmpeg failed');
    }

    /**
     * One MP4 file with the moov atom up front, so browsers start playing
     * before it's downloaded: for players and places without HLS.
     *
     * @param callable(): bool $shouldStop Asked every second: true kills ffmpeg
     */
    public static function encodeProgressive(string $inputPath, string $format, Variant $variant, string $outputPath, callable $shouldStop): void
    {
        self::run('ffmpeg -hide_banner -y' . self::restrictedInput($format) . ' -i ' . escapeshellarg($inputPath)
            . ' -vf ' . escapeshellarg("scale=-2:{$variant->maxHeight}:force_original_aspect_ratio=decrease:force_divisible_by=2")
            . ' -c:v libx264 -b:v ' . $variant->videoBitrate . 'k'
            . ' -maxrate ' . $variant->maxRate() . 'k -bufsize ' . $variant->bufSize() . 'k'
            . ' -c:a aac -b:a ' . $variant->audioBitrate . 'k -ac 2 -ar 48000'
            . ' -movflags +faststart -f mp4'
            . ' ' . escapeshellarg($outputPath), $shouldStop, 'ffmpeg failed on the MP4');
    }

    /**
     * Runs an encoding that can take hours, asking $shouldStop every second
     * (Command would block until ffmpeg exits, released from the queue or not).
     *
     * @throws EncodingStopped When $shouldStop returned true and ffmpeg was killed
     */
    private static function run(string $command, callable $shouldStop, string $errorPrefix): void
    {
        // stderr to a file: ffmpeg's progress output would fill a pipe nobody reads and block it
        $log = tempnam(sys_get_temp_dir(), 'ffmpeg_');
        // exec: the process is ffmpeg itself, not a shell that would survive the kill
        $process = proc_open('exec ' . $command, [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', $log, 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            @unlink($log);
            throw new Exception("$errorPrefix: could not start it");
        }

        try {
            while (($status = proc_get_status($process))['running']) {
                if ($shouldStop()) {
                    proc_terminate($process, 9);
                    proc_close($process);
                    throw new EncodingStopped('Stopped from the queue');
                }

                sleep(1);
            }

            proc_close($process);

            if ($status['exitcode'] !== 0) {
                // The end of the log: the error, not the banner
                throw new Exception("$errorPrefix: " . trim(substr((string)file_get_contents($log), -2000)));
            }
        } finally {
            @unlink($log);
        }
    }

    private static function probeResolution(string $inputOptions, string $path): ?array
    {
        exec('ffprobe -v error' . $inputOptions . ' -select_streams v:0 -show_entries stream=width,height -of csv=p=0 ' . escapeshellarg($path), $output, $exitCode);

        if ($exitCode !== 0 || empty($output[0])) {
            return null;
        }

        return array_map('intval', explode(',', $output[0]));
    }

    private static function restrictedInput(?string $format = null): string
    {
        $options = ' -protocol_whitelist file -format_whitelist ' . escapeshellarg(implode(',', self::ALLOWED_FORMATS));

        return $format !== null ? $options . ' -f ' . escapeshellarg($format) : $options;
    }
}
