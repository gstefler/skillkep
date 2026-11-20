<?php

namespace Tests\Feature\Courses;

use App\Models\CourseUpload;
use Illuminate\Testing\TestResponse;
use ZipArchive;

trait BuildsCourseArchives
{
    /**
     * @param  array<string, string>  $entries  Map of entry name to contents.
     */
    protected function buildZip(array $entries, string $fileName = 'kurzus.zip'): string
    {
        $directory = sys_get_temp_dir().'/course-zip-'.uniqid();
        mkdir($directory);

        $zipPath = $directory.'/'.$fileName;
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE);

        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }

        $zip->close();

        return $zipPath;
    }

    protected function questionsJson(): string
    {
        return (string) json_encode([
            ['type' => 'true_false', 'prompt' => 'A Laravel PHP-keretrendszer?', 'answer' => true, 'points' => 1],
            ['type' => 'select_one', 'prompt' => 'Melyik a főváros?', 'options' => ['Budapest', 'Debrecen'], 'answer' => 'Budapest', 'points' => 1],
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function startPayload(string $zipPath, array $overrides = []): array
    {
        return array_merge([
            'filename' => basename($zipPath),
            'size' => filesize($zipPath),
            'last_modified' => 1_700_000_000_000,
            'pass_score' => 60,
            'lock_modules' => false,
        ], $overrides);
    }

    protected function putChunk(CourseUpload $upload, int $index, string $contents): TestResponse
    {
        return $this->call(
            'PUT',
            route('manage.courses.uploads.chunk', ['upload' => $upload, 'index' => $index]),
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/octet-stream', 'HTTP_ACCEPT' => 'application/json'],
            $contents,
        );
    }

    /**
     * Upload a ZIP through the whole chunked flow and return the upload record.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function uploadZip(string $zipPath, array $overrides = []): CourseUpload
    {
        $response = $this->postJson(route('manage.courses.uploads.store'), $this->startPayload($zipPath, $overrides))
            ->assertSuccessful();

        $upload = CourseUpload::query()->findOrFail($response->json('id'));
        $contents = (string) file_get_contents($zipPath);

        for ($index = 0; $index < $upload->total_chunks; $index++) {
            $this->putChunk($upload, $index, substr($contents, $index * $upload->chunk_size, $upload->chunk_size))
                ->assertSuccessful();
        }

        $this->postJson(route('manage.courses.uploads.complete', $upload))->assertSuccessful();

        return $upload->fresh();
    }
}
