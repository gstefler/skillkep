<?php

namespace App\Services;

use App\Enums\MaterialType;
use App\Enums\QuestionType;
use App\Enums\VideoStatus;
use App\Models\Course;
use App\Models\Module;
use App\Models\Test;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Imports a course from an extracted folder:
 *
 * - every subfolder whose name starts with a number is a module, in numeric order;
 * - the first subfolder without a number is the closing exam, placed last;
 * - PDF and MP4 files in a module folder become its materials;
 * - a JSON file named like "test"/"teszt" (or the only JSON file) is the module test.
 *
 * Videos are only stored here; HLS transcoding is queued afterwards.
 */
class CourseImportService
{
    public int $materialCount = 0;

    public int $questionCount = 0;

    public int $moduleCount = 0;

    /**
     * @param  (Closure(int, int): void)|null  $onProgress  Called after each module with (modules done, modules total).
     */
    public function import(
        string $directory,
        int $passScore = 60,
        bool $lockModules = false,
        ?string $title = null,
        ?User $creator = null,
        ?Closure $onProgress = null,
    ): Course {
        $course = null;

        try {
            return $this->importCourse($course, $directory, $passScore, $lockModules, $title, $creator, $onProgress);
        } catch (Throwable $e) {
            if ($course !== null) {
                $course->deleteFiles();
                $course->delete();
            }

            throw $e;
        }
    }

    /**
     * @param-out Course $course
     *
     * @param  (Closure(int, int): void)|null  $onProgress
     */
    private function importCourse(
        ?Course &$course,
        string $directory,
        int $passScore,
        bool $lockModules,
        ?string $title,
        ?User $creator,
        ?Closure $onProgress,
    ): Course {
        $this->materialCount = 0;
        $this->questionCount = 0;
        $this->moduleCount = 0;

        $subdirs = array_filter(
            glob(rtrim($directory, '/').'/*') ?: [],
            fn (string $path): bool => is_dir($path)
                && ! str_starts_with(basename($path), '.')
                && basename($path) !== '__MACOSX',
        );

        $numbered = [];
        $examFolders = [];

        foreach ($subdirs as $path) {
            if (preg_match('/^\d+/', basename($path)) === 1) {
                $numbered[] = $path;
            } else {
                $examFolders[] = $path;
            }
        }

        usort($numbered, fn (string $a, string $b): int => (int) basename($a) <=> (int) basename($b));
        sort($examFolders);

        $totalModules = count($numbered) + ($examFolders === [] ? 0 : 1);
        $title ??= basename($directory);

        $course = Course::query()->create([
            'title' => $title,
            'slug' => $this->generateUniqueSlug($title),
            'is_published' => false,
            'sequential_modules' => $lockModules,
            'created_by_user_id' => $creator?->getKey(),
        ]);

        $previousModule = null;
        $folders = array_map(fn (string $path) => [$path, (int) basename($path)], $numbered);

        if ($examFolders !== []) {
            $folders[] = [$examFolders[0], null];
        }

        foreach ($folders as [$moduleDir, $order]) {
            $isExam = $order === null;
            $order ??= $previousModule !== null ? $previousModule->order + 1 : 1;

            // The closing exam always requires the last module; the others only when locked.
            $prerequisiteId = ($lockModules || $isExam) ? $previousModule?->id : null;

            $module = $course->modules()->create([
                'title' => basename($moduleDir),
                'order' => $order,
                'prerequisite_module_id' => $prerequisiteId,
            ]);

            $this->loadMaterials($moduleDir, $course, $module);

            $testFile = $this->findTestFile($moduleDir);
            if ($testFile !== null) {
                $this->loadTest($testFile, $module, $passScore);
            }

            $previousModule = $module;
            $this->moduleCount++;
            $onProgress?->__invoke($this->moduleCount, $totalModules);
        }

        return $course->fresh(['modules.test.questions.options', 'modules.materials']) ?? $course;
    }

    private function loadMaterials(string $dir, Course $course, Module $module): void
    {
        $files = array_filter(glob($dir.'/*') ?: [], is_file(...));
        natcasesort($files);

        foreach ($files as $filePath) {
            $filename = basename($filePath);

            if (str_starts_with($filename, '.')) {
                continue;
            }

            $type = MaterialType::fromExtension(pathinfo($filename, PATHINFO_EXTENSION));

            if ($type === null) {
                continue;
            }

            $material = $module->materials()->create([
                'title' => pathinfo($filename, PATHINFO_FILENAME),
                'type' => $type,
                'path' => '',
                'size' => (int) filesize($filePath),
                'order' => ($module->materials()->max('order') ?? 0) + 1,
                'video_status' => $type === MaterialType::Video ? VideoStatus::Pending : null,
            ]);

            $storedName = $type === MaterialType::Video
                ? 'source.'.strtolower(pathinfo($filename, PATHINFO_EXTENSION))
                : 'document.pdf';
            $storagePath = "courses/{$course->id}/materials/{$material->id}/{$storedName}";
            $stream = fopen($filePath, 'rb');

            if ($stream === false) {
                throw new RuntimeException("Nem olvasható fájl: {$filename}");
            }

            try {
                Storage::disk('local')->put($storagePath, $stream);
            } finally {
                if (is_resource($stream)) {
                    fclose($stream);
                }
            }

            $material->update(['path' => $storagePath]);
            $this->materialCount++;
        }
    }

    private function loadTest(string $filePath, Module $module, int $passScore): void
    {
        $items = json_decode((string) file_get_contents($filePath), true);

        if (! is_array($items) || $items === []) {
            return;
        }

        $test = $module->test()->create([
            'title' => $module->title.' – teszt',
            'pass_score' => $passScore,
        ]);

        foreach (array_values($items) as $index => $item) {
            if (is_array($item)) {
                $this->loadQuestion($test, $item, $index + 1);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function loadQuestion(Test $test, array $item, int $order): void
    {
        $type = QuestionType::tryFrom((string) ($item['type'] ?? ''));

        if ($type === null) {
            return;
        }

        $correctAnswer = match ($type) {
            QuestionType::TrueFalse => $this->normaliseTrueFalse($item['answer'] ?? false),
            QuestionType::Write => (string) ($item['answer'] ?? ''),
            default => null,
        };

        $question = $test->questions()->create([
            'type' => $type,
            'prompt' => (string) ($item['prompt'] ?? $item['question'] ?? ''),
            'points' => (int) ($item['points'] ?? 1),
            'order' => $order,
            'correct_answer' => $correctAnswer,
        ]);

        if ($type === QuestionType::SelectOne || $type === QuestionType::SelectMultiple) {
            $correctAnswers = array_map('strval', (array) ($item['answer'] ?? []));

            foreach (array_values((array) ($item['options'] ?? [])) as $optionIndex => $label) {
                $question->options()->create([
                    'label' => (string) $label,
                    'is_correct' => in_array((string) $label, $correctAnswers, true),
                    'order' => $optionIndex + 1,
                ]);
            }
        } elseif ($type === QuestionType::Pair) {
            foreach (array_values((array) ($item['pairs'] ?? [])) as $optionIndex => $pair) {
                [$label, $match] = is_array($pair)
                    ? [(string) ($pair['key'] ?? ''), (string) ($pair['value'] ?? '')]
                    : [(string) $optionIndex, (string) $pair];

                $question->options()->create([
                    'label' => $label,
                    'match' => $match,
                    'is_correct' => false,
                    'order' => $optionIndex + 1,
                ]);
            }
        }

        $this->questionCount++;
    }

    private function normaliseTrueFalse(mixed $answer): string
    {
        if (is_bool($answer)) {
            return $answer ? 'true' : 'false';
        }

        return in_array(strtolower((string) $answer), ['true', 'igaz', 'yes', 'igen', '1'], true) ? 'true' : 'false';
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'kurzus';
        $slug = $base;
        $counter = 2;

        while (Course::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    private function findTestFile(string $dir): ?string
    {
        $jsonFiles = array_values(array_filter(
            glob(rtrim($dir, '/').'/*') ?: [],
            fn (string $path): bool => is_file($path)
                && strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json'
                && ! str_starts_with(basename($path), '.'),
        ));

        foreach ($jsonFiles as $path) {
            if (preg_match('/test|teszt/i', basename($path)) === 1) {
                return $path;
            }
        }

        return count($jsonFiles) === 1 ? $jsonFiles[0] : null;
    }
}
