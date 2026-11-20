<?php

namespace App\Jobs;

use App\Enums\CourseUploadStatus;
use App\Enums\MaterialType;
use App\Models\CourseUpload;
use App\Models\Material;
use App\Services\CourseImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Extracts a fully uploaded course ZIP, imports it, then queues the HLS
 * transcoding of its videos. Runs on its own `imports` queue: copying a
 * multi-gigabyte archive does not fit the default queue's timeout, and it
 * should not wait behind (or block) the ffmpeg jobs on the `video` queue.
 */
class ProcessCourseUpload implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    private float $lastReportedAt = 0.0;

    public function __construct(public CourseUpload $upload)
    {
        // Pushed on the default Redis connection; the Horizon `imports`
        // supervisor reads the same list through `redis-imports`.
        $this->onQueue('imports');
    }

    public function handle(CourseImportService $importer): void
    {
        $upload = $this->upload->fresh();

        if ($upload === null || $upload->status !== CourseUploadStatus::Processing) {
            return;
        }

        $extractRoot = Storage::disk('local')->path('course-imports/'.Str::uuid()->toString());
        File::ensureDirectoryExists($extractRoot);

        try {
            $this->reportProgress($upload, 'extracting', 0, force: true);
            $this->extract($upload, $extractRoot);

            $importRoot = $this->resolveCourseRoot($extractRoot);
            $title = $importRoot === $extractRoot
                ? pathinfo($upload->filename, PATHINFO_FILENAME)
                : null;

            $this->reportProgress($upload, 'importing', 0, force: true);

            $course = $importer->import(
                directory: $importRoot,
                passScore: $upload->pass_score,
                lockModules: $upload->lock_modules,
                title: $title,
                creator: $upload->user,
                onProgress: fn (int $done, int $total) => $this->reportProgress(
                    $upload,
                    'importing',
                    $total > 0 ? intdiv($done * 100, $total) : 100,
                    force: true,
                ),
            );

            $upload->forceFill([
                'status' => CourseUploadStatus::Completed,
                'stage' => null,
                'stage_progress' => 100,
                'course_id' => $course->getKey(),
                'result' => [
                    'modules' => $importer->moduleCount,
                    'materials' => $importer->materialCount,
                    'questions' => $importer->questionCount,
                ],
                'error' => null,
            ])->save();

            $upload->deleteFiles();
        } catch (Throwable $e) {
            report($e);
            $this->markFailed($upload, $e);

            return;
        } finally {
            File::deleteDirectory($extractRoot);
        }

        // The course is imported; videos become playable as their transcoding finishes.
        $course->materials()
            ->where('type', MaterialType::Video)
            ->each(fn (Material $material) => TranscodeVideo::dispatch($material));
    }

    public function failed(Throwable $exception): void
    {
        $upload = $this->upload->fresh();

        if ($upload !== null && $upload->status === CourseUploadStatus::Processing) {
            $this->markFailed($upload, $exception);
        }
    }

    private function extract(CourseUpload $upload, string $extractRoot): void
    {
        $archive = new ZipArchive;

        if ($archive->open($upload->archivePath()) !== true) {
            throw new RuntimeException('A feltöltött fájl nem érvényes ZIP-archívum.');
        }

        try {
            $entries = [];
            $totalBytes = 0;

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);

                if ($stat === false) {
                    continue;
                }

                // Never write outside the extraction directory (zip slip).
                $segments = explode('/', str_replace('\\', '/', $stat['name']));

                if (in_array('..', $segments, true) || str_starts_with($stat['name'], '/')) {
                    throw new RuntimeException("Érvénytelen elérési út az archívumban: {$stat['name']}");
                }

                $entries[] = $stat;
                $totalBytes += $stat['size'];
            }

            $doneBytes = 0;

            foreach ($entries as $stat) {
                if (! $archive->extractTo($extractRoot, $stat['name'])) {
                    throw new RuntimeException("Nem sikerült kicsomagolni: {$stat['name']}");
                }

                $doneBytes += $stat['size'];
                $this->reportProgress(
                    $upload,
                    'extracting',
                    $totalBytes > 0 ? intdiv($doneBytes * 100, $totalBytes) : 100,
                );
            }
        } finally {
            $archive->close();
        }
    }

    /**
     * If the ZIP contains a single top-level course folder, import from inside
     * it. Otherwise import from the extraction root.
     */
    private function resolveCourseRoot(string $extractRoot): string
    {
        $entries = array_values(array_filter(
            glob($extractRoot.'/*') ?: [],
            fn (string $path): bool => basename($path) !== '__MACOSX',
        ));

        // A lone folder is the course folder only if it holds module folders;
        // otherwise it is the single module of a ZIP without a wrapper folder.
        if (count($entries) === 1 && is_dir($entries[0]) && glob($entries[0].'/*', GLOB_ONLYDIR) !== []) {
            return $entries[0];
        }

        return $extractRoot;
    }

    private function reportProgress(CourseUpload $upload, string $stage, int $progress, bool $force = false): void
    {
        $now = microtime(true);

        if (! $force && $now - $this->lastReportedAt < 1.0) {
            return;
        }

        $this->lastReportedAt = $now;

        $upload->forceFill(['stage' => $stage, 'stage_progress' => min(100, $progress)])->save();
    }

    private function markFailed(CourseUpload $upload, Throwable $e): void
    {
        $upload->forceFill([
            'status' => CourseUploadStatus::Failed,
            'stage' => null,
            'error' => Str::limit($e->getMessage(), 500),
        ])->save();

        $upload->deleteFiles();
    }
}
