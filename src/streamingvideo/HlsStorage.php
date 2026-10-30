<?php

namespace rondodevs\toolkit\streamingvideo;

use Craft;
use craft\base\FsInterface;
use craft\elements\Asset;
use Exception;
use Throwable;

/**
 * An asset's HLS files, next to the video in the same volume:
 * `__hls__/{asset-uid}/`, inside the volume's subpath.
 */
final class HlsStorage
{
    private const DIRECTORY = '__hls__';
    private const UPLOAD_ATTEMPTS = 3;

    private readonly string $directory;

    public function __construct(private readonly Asset $asset)
    {
        $this->directory = self::DIRECTORY . '/' . $asset->uid . '/';
    }

    /**
     * Absolute URL of the master playlist, or null when the volume has no Base URL.
     */
    public function masterPlaylistUrl(): ?string
    {
        return $this->url(MasterPlaylist::FILENAME);
    }

    /**
     * Absolute URL of one of the asset's files, or null when the volume has no Base URL.
     */
    public function url(string $file): ?string
    {
        $baseUrl = $this->asset->getVolume()->getRootUrl();

        if (!$baseUrl) {
            return null;
        }

        return rtrim($baseUrl, '/') . '/' . $this->directory . $file;
    }

    /**
     * Uploads local files in the order given, retrying each with backoff.
     *
     * @param string[] $paths
     */
    public function upload(array $paths): void
    {
        foreach ($paths as $path) {
            $this->withRetries(function() use ($path) {
                $stream = fopen($path, 'rb');

                if (!$stream) {
                    throw new Exception('Failed to open file for reading: ' . basename($path));
                }

                try {
                    $this->fs()->writeFileFromStream($this->path(basename($path)), $stream);
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            });
        }
    }

    /**
     * Deletes segments and playlists left by a previous encoding.
     *
     * @param string $segmentTag Tag of the encoding that is now live
     * @param string[] $playlists Playlist file names of the encoding that is now live
     */
    public function deleteStaleFiles(string $segmentTag, array $playlists): void
    {
        try {
            foreach ($this->fs()->getFileList($this->path(), false) as $listing) {
                $name = $listing->getBasename();
                $isStale = !$listing->getIsDir() && (
                    (str_ends_with($name, '.ts') && !str_contains($name, "_{$segmentTag}_"))
                    || (str_ends_with($name, '.m3u8') && !in_array($name, $playlists, true))
                );

                if ($isStale) {
                    $this->fs()->deleteFile($this->path($name));
                }
            }
        } catch (Throwable $e) {
            // The new encoding is already live: leftovers only waste disk space
            Craft::warning("Could not delete stale HLS files in {$this->path()}: " . $e->getMessage(), __METHOD__);
        }
    }

    public function deleteAll(): void
    {
        try {
            if (!$this->fs()->directoryExists($this->path())) {
                return;
            }

            // Files first: not every filesystem deletes a directory that isn't empty
            foreach ($this->fs()->getFileList($this->path(), false) as $listing) {
                if (!$listing->getIsDir()) {
                    $this->fs()->deleteFile($this->path($listing->getBasename()));
                }
            }

            $this->fs()->deleteDirectory($this->path());
        } catch (Throwable $e) {
            Craft::warning("Could not delete the HLS files in {$this->path()}: " . $e->getMessage(), __METHOD__);
        }
    }

    /**
     * Path of a file (or of the directory itself) on the volume's filesystem.
     */
    private function path(string $file = ''): string
    {
        return $this->asset->getVolume()->getSubpath() . $this->directory . $file;
    }

    private function fs(): FsInterface
    {
        return $this->asset->getVolume()->getFs();
    }

    private function withRetries(callable $operation): void
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $operation();

                return;
            } catch (Throwable $e) {
                Craft::warning("HLS upload attempt {$attempt}/" . self::UPLOAD_ATTEMPTS . ' failed: ' . $e->getMessage(), __METHOD__);

                if ($attempt >= self::UPLOAD_ATTEMPTS) {
                    throw $e;
                }

                // 1s, 2s, 4s...
                sleep(2 ** ($attempt - 1));
            }
        }
    }
}
