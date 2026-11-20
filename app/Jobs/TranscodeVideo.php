<?php

namespace App\Jobs;

use App\Enums\VideoStatus;
use App\Models\Material;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Transcodes a video material to AES-128 encrypted HLS (ADR-0004).
 *
 * - The key and the playlist stay on the private local disk; Laravel serves
 *   both after checking access (MaterialController).
 * - The encrypted segments go to a random, unguessable directory on the
 *   public `hls` disk, which the web server serves statically.
 */
class TranscodeVideo implements ShouldQueue
{
    use Queueable;

    /** Written into the playlist by ffmpeg, replaced by the key URL when served. */
    public const string KEY_URI_PLACEHOLDER = 'skillkep-key://material';

    public int $timeout = 3600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public Material $material)
    {
        // Pushed on the default Redis connection; the Horizon `video`
        // supervisor reads the same list through `redis-video`.
        $this->onQueue('video');
    }

    public function handle(): void
    {
        $material = $this->material->fresh();

        if ($material === null || ! $material->isVideo()) {
            return;
        }

        $material->forceFill(['video_status' => VideoStatus::Processing, 'video_error' => null])->save();

        $local = Storage::disk('local');
        $hls = Storage::disk('hls');
        $source = $local->path($material->path);
        $workDirectory = $local->path($material->videoDirectory());
        $outputDirectory = Str::lower(Str::random(40));

        try {
            if (! is_file($source)) {
                throw new RuntimeException('A forrásvideó nem található.');
            }

            File::ensureDirectoryExists($workDirectory);
            File::ensureDirectoryExists($hls->path($outputDirectory));

            $keyFile = $local->path($material->keyPath());
            file_put_contents($keyFile, random_bytes(16));

            $keyInfoFile = $workDirectory.'/keyinfo';
            file_put_contents($keyInfoFile, implode("\n", [
                self::KEY_URI_PLACEHOLDER,
                $keyFile,
                bin2hex(random_bytes(16)),
            ])."\n");

            $result = Process::timeout($this->timeout - 60)->run([
                config('services.ffmpeg.binary', 'ffmpeg'),
                '-hide_banner', '-nostdin', '-y', '-loglevel', 'error',
                '-i', $source,
                '-map', '0:v:0', '-map', '0:a:0?',
                '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '22', '-pix_fmt', 'yuv420p',
                '-vf', "scale=-2:'min(1080,trunc(ih/2)*2)'",
                '-force_key_frames', 'expr:gte(t,n_forced*6)',
                '-c:a', 'aac', '-b:a', '128k', '-ac', '2',
                '-f', 'hls', '-hls_time', '6', '-hls_playlist_type', 'vod',
                '-hls_key_info_file', $keyInfoFile,
                '-hls_segment_filename', $hls->path($outputDirectory).'/%05d.ts',
                $local->path($material->playlistPath()),
            ]);

            @unlink($keyInfoFile);

            if ($result->failed()) {
                throw new RuntimeException('Az ffmpeg hibával leállt: '.Str::limit(trim($result->errorOutput()), 400));
            }

            $previousDirectory = $material->hls_directory;

            $material->forceFill([
                'video_status' => VideoStatus::Ready,
                'hls_directory' => $outputDirectory,
                'duration_seconds' => $this->probeDuration($source),
                'video_error' => null,
            ])->save();

            if ($previousDirectory !== null && $previousDirectory !== $outputDirectory) {
                $hls->deleteDirectory($previousDirectory);
            }
        } catch (Throwable $e) {
            $hls->deleteDirectory($outputDirectory);
            $this->markFailed($material, $e);

            throw $e;
        }
    }

    public function failed(Throwable $exception): void
    {
        $material = $this->material->fresh();

        if ($material !== null && $material->video_status !== VideoStatus::Failed) {
            $this->markFailed($material, $exception);
        }
    }

    private function probeDuration(string $source): ?int
    {
        $result = Process::timeout(60)->run([
            config('services.ffmpeg.ffprobe', 'ffprobe'),
            '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1',
            $source,
        ]);

        $seconds = trim($result->output());

        return $result->successful() && is_numeric($seconds) ? (int) round((float) $seconds) : null;
    }

    private function markFailed(Material $material, Throwable $e): void
    {
        $material->forceFill([
            'video_status' => VideoStatus::Failed,
            'video_error' => Str::limit($e->getMessage(), 500),
        ])->save();
    }
}
