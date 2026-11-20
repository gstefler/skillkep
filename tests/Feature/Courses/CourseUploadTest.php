<?php

namespace Tests\Feature\Courses;

use App\Enums\CourseUploadStatus;
use App\Enums\MaterialType;
use App\Enums\VideoStatus;
use App\Jobs\ProcessCourseUpload;
use App\Jobs\TranscodeVideo;
use App\Models\Company;
use App\Models\Course;
use App\Models\CourseUpload;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourseUploadTest extends TestCase
{
    use BuildsCourseArchives, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('hls');
        Queue::fake([TranscodeVideo::class]);
    }

    private function superAdmin(): User
    {
        $user = User::factory()->superAdmin()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_a_course_is_imported_from_a_chunked_zip_upload(): void
    {
        $superAdmin = $this->superAdmin();

        $upload = $this->uploadZip($this->buildZip([
            'Bevezető kurzus/1. Első modul/teszt.json' => $this->questionsJson(),
            'Bevezető kurzus/1. Első modul/Jegyzet.pdf' => '%PDF-1.4 jegyzet',
            'Bevezető kurzus/1. Első modul/Előadás.mp4' => 'fake mp4',
        ]), ['pass_score' => 70, 'lock_modules' => true]);

        $this->assertSame(CourseUploadStatus::Completed, $upload->status);
        $this->assertSame(['modules' => 1, 'materials' => 2, 'questions' => 2], $upload->result);

        $course = Course::query()->sole();
        $this->assertSame('Bevezető kurzus', $course->title);
        $this->assertFalse($course->is_published);
        $this->assertSame($superAdmin->id, $course->created_by_user_id);
        $this->assertSame($course->id, $upload->course_id);

        $module = $course->modules()->sole();
        $this->assertSame('1. Első modul', $module->title);
        $this->assertSame(70, $module->test->pass_score);
        $this->assertSame(2, $module->test->questions()->count());

        $pdf = $module->materials()->where('type', MaterialType::Pdf)->sole();
        Storage::disk('local')->assertExists($pdf->path);

        $video = $module->materials()->where('type', MaterialType::Video)->sole();
        $this->assertSame(VideoStatus::Pending, $video->video_status);
        Storage::disk('local')->assertExists($video->path);
        Queue::assertPushed(TranscodeVideo::class, fn (TranscodeVideo $job) => $job->material->is($video));

        // The archive and its chunks are gone once the import is done.
        $this->assertDirectoryDoesNotExist($upload->directory());
    }

    public function test_numbered_folders_are_ordered_and_the_unnumbered_exam_comes_last(): void
    {
        $this->superAdmin();

        $this->uploadZip($this->buildZip([
            'IKT/01_modul/01_modul_teszt.json' => $this->questionsJson(),
            'IKT/02_modul/02_modul_teszt.json' => $this->questionsJson(),
            'IKT/10_modul/10_modul_teszt.json' => $this->questionsJson(),
            'IKT/Záróvizsga/12_zaroteszt.json' => $this->questionsJson(),
        ]));

        $modules = Course::query()->sole()->modules()->get();

        $this->assertSame(['01_modul', '02_modul', '10_modul', 'Záróvizsga'], $modules->pluck('title')->all());
        $this->assertSame([1, 2, 10, 11], $modules->pluck('order')->all());
        $this->assertTrue($modules->every(fn ($module) => $module->test !== null));
        // Without locking only the exam requires the last module.
        $this->assertSame([null, null, null, $modules[2]->id], $modules->pluck('prerequisite_module_id')->all());
    }

    public function test_a_zip_without_a_wrapper_folder_is_named_after_the_file(): void
    {
        $this->superAdmin();

        $this->uploadZip($this->buildZip([
            '1_alapok/Kerdesek.JSON' => $this->questionsJson(),
        ], 'Munkavédelem.zip'));

        $course = Course::query()->sole();
        $this->assertSame('Munkavédelem', $course->title);
        $this->assertNotNull($course->modules()->sole()->test);
    }

    public function test_a_multi_chunk_archive_can_be_sent_out_of_order(): void
    {
        $this->superAdmin();

        $zipPath = $this->buildZip([
            'Nagy kurzus/1. Modul/teszt.json' => $this->questionsJson(),
            'Nagy kurzus/1. Modul/video.mp4' => random_bytes(CourseUpload::CHUNK_SIZE + 1024),
        ]);

        $upload = CourseUpload::query()->findOrFail(
            $this->postJson(route('manage.courses.uploads.store'), $this->startPayload($zipPath))->json('id'),
        );
        $this->assertSame(2, $upload->total_chunks);

        $contents = (string) file_get_contents($zipPath);
        $this->putChunk($upload, 1, substr($contents, CourseUpload::CHUNK_SIZE))->assertOk();
        $this->putChunk($upload, 0, substr($contents, 0, CourseUpload::CHUNK_SIZE))->assertOk();
        $this->postJson(route('manage.courses.uploads.complete', $upload))->assertOk();

        $this->assertSame(CourseUploadStatus::Completed, $upload->fresh()->status);
        $this->assertSame(1, Course::query()->count());
    }

    public function test_starting_the_same_file_again_resumes_and_reports_the_received_chunks(): void
    {
        $this->superAdmin();
        $zipPath = $this->buildZip(['Kurzus/1. Modul/video.mp4' => random_bytes(CourseUpload::CHUNK_SIZE + 10)]);
        $contents = (string) file_get_contents($zipPath);

        $first = $this->postJson(route('manage.courses.uploads.store'), $this->startPayload($zipPath))->assertCreated();
        $upload = CourseUpload::query()->findOrFail($first->json('id'));
        $this->putChunk($upload, 0, substr($contents, 0, CourseUpload::CHUNK_SIZE))->assertOk();

        $this->postJson(route('manage.courses.uploads.store'), $this->startPayload($zipPath, ['pass_score' => 80]))
            ->assertOk()
            ->assertJsonPath('id', $upload->id)
            ->assertJsonPath('received', [0])
            ->assertJsonPath('status', 'uploading');

        $this->assertSame(80, $upload->fresh()->pass_score);
        $this->assertSame(1, CourseUpload::query()->count());
    }

    public function test_another_users_upload_is_never_resumed_or_reachable(): void
    {
        $zipPath = $this->buildZip(['Kurzus/1. Modul/teszt.json' => $this->questionsJson()]);

        $this->superAdmin();
        $upload = CourseUpload::query()->findOrFail(
            $this->postJson(route('manage.courses.uploads.store'), $this->startPayload($zipPath))->json('id'),
        );

        $this->superAdmin();
        $this->postJson(route('manage.courses.uploads.store'), $this->startPayload($zipPath))
            ->assertCreated()
            ->assertJsonMissing(['id' => $upload->id]);

        $this->getJson(route('manage.courses.uploads.show', $upload))->assertNotFound();
        $this->putChunk($upload, 0, 'x')->assertNotFound();
        $this->deleteJson(route('manage.courses.uploads.destroy', $upload))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('invalidStarts')]
    public function test_the_start_request_is_validated(array $overrides, string $field): void
    {
        $this->superAdmin();
        $zipPath = $this->buildZip(['a.txt' => 'x']);

        $this->postJson(route('manage.courses.uploads.store'), $this->startPayload($zipPath, $overrides))
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
    }

    /**
     * @return array<string, array{array<string, mixed>, string}>
     */
    public static function invalidStarts(): array
    {
        return [
            'not a zip' => [['filename' => 'kurzus.rar'], 'filename'],
            'too large' => [['size' => CourseUpload::MAX_SIZE + 1], 'size'],
            'pass score above 100' => [['pass_score' => 101], 'pass_score'],
        ];
    }

    public function test_a_chunk_of_the_wrong_size_is_rejected_and_not_marked_received(): void
    {
        $this->superAdmin();
        $zipPath = $this->buildZip(['Kurzus/1. Modul/teszt.json' => $this->questionsJson()]);
        $upload = CourseUpload::query()->findOrFail(
            $this->postJson(route('manage.courses.uploads.store'), $this->startPayload($zipPath))->json('id'),
        );

        $this->putChunk($upload, 0, 'túl rövid')->assertUnprocessable();

        $this->assertSame([], $upload->receivedChunks());
        $this->putChunk($upload, 5, 'x')->assertNotFound();
    }

    public function test_completion_is_refused_while_chunks_are_missing(): void
    {
        $this->superAdmin();
        $zipPath = $this->buildZip(['Kurzus/1. Modul/video.mp4' => random_bytes(CourseUpload::CHUNK_SIZE + 10)]);
        $upload = CourseUpload::query()->findOrFail(
            $this->postJson(route('manage.courses.uploads.store'), $this->startPayload($zipPath))->json('id'),
        );
        $this->putChunk($upload, 0, substr((string) file_get_contents($zipPath), 0, CourseUpload::CHUNK_SIZE))->assertOk();

        $this->postJson(route('manage.courses.uploads.complete', $upload))
            ->assertUnprocessable()
            ->assertJsonPath('received', [0]);

        $this->assertSame(CourseUploadStatus::Uploading, $upload->fresh()->status);
    }

    public function test_a_completed_upload_that_is_not_a_zip_is_discarded(): void
    {
        $this->superAdmin();
        $contents = str_repeat('nem zip ', 10);
        $upload = CourseUpload::query()->findOrFail($this->postJson(route('manage.courses.uploads.store'), [
            'filename' => 'hamis.zip',
            'size' => strlen($contents),
            'last_modified' => 1,
            'pass_score' => 60,
        ])->json('id'));

        $this->putChunk($upload, 0, $contents)->assertOk();
        $this->postJson(route('manage.courses.uploads.complete', $upload))->assertUnprocessable();

        $this->assertModelMissing($upload);
        $this->assertDirectoryDoesNotExist($upload->directory());
    }

    public function test_completion_queues_the_import_on_the_imports_queue(): void
    {
        Queue::fake();
        $this->superAdmin();

        $upload = $this->uploadZip($this->buildZip(['Kurzus/1. Modul/teszt.json' => $this->questionsJson()]));

        $this->assertSame(CourseUploadStatus::Processing, $upload->status);
        $this->assertSame('queued', $upload->stage);
        Queue::assertPushedOn('imports', ProcessCourseUpload::class);
        $this->assertSame(0, Course::query()->count());
    }

    public function test_a_broken_archive_marks_the_upload_failed_and_leaves_nothing_behind(): void
    {
        $this->superAdmin();

        $upload = $this->uploadZip($this->buildZip([
            'Kurzus/../../kitörés.txt' => 'x',
        ]));

        $this->assertSame(CourseUploadStatus::Failed, $upload->status);
        $this->assertNotNull($upload->error);
        $this->assertSame(0, Course::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('course-imports'));
    }

    public function test_the_owner_can_discard_an_unfinished_upload(): void
    {
        $this->superAdmin();
        $zipPath = $this->buildZip(['Kurzus/1. Modul/teszt.json' => $this->questionsJson()]);
        $upload = CourseUpload::query()->findOrFail(
            $this->postJson(route('manage.courses.uploads.store'), $this->startPayload($zipPath))->json('id'),
        );

        $this->deleteJson(route('manage.courses.uploads.destroy', $upload))->assertNoContent();

        $this->assertModelMissing($upload);
        $this->assertFalse(File::isDirectory($upload->directory()));
    }

    public function test_only_superadmins_can_upload_courses(): void
    {
        $admin = User::factory()->admin()->create(['company_id' => Company::factory()]);

        $this->actingAs($admin)->get(route('manage.courses.uploads.create'))->assertForbidden();
        $this->actingAs($admin)->postJson(route('manage.courses.uploads.store'), [])->assertForbidden();
        $this->actingAs(User::factory()->create())->postJson(route('manage.courses.uploads.store'), [])->assertForbidden();
    }

    public function test_the_upload_page_lists_recent_uploads(): void
    {
        $superAdmin = $this->superAdmin();
        CourseUpload::factory()->for($superAdmin)->create(['filename' => 'félbehagyott.zip']);
        CourseUpload::factory()->create(['filename' => 'másé.zip']);

        $this->get(route('manage.courses.uploads.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('manage/courses/Upload')
                ->has('recentUploads', 1)
                ->where('recentUploads.0.filename', 'félbehagyott.zip')
                ->where('chunkSize', CourseUpload::CHUNK_SIZE));
    }

    public function test_pruning_discards_abandoned_uploads_and_fails_interrupted_ones(): void
    {
        $abandoned = CourseUpload::factory()->create(['updated_at' => now()->subDays(4)]);
        $fresh = CourseUpload::factory()->create();
        $interrupted = CourseUpload::factory()->create([
            'status' => CourseUploadStatus::Processing,
            'updated_at' => now()->subHours(3),
        ]);

        $this->artisan('course-uploads:prune')->assertSuccessful();

        $this->assertModelMissing($abandoned);
        $this->assertModelExists($fresh);
        $this->assertSame(CourseUploadStatus::Failed, $interrupted->fresh()->status);
    }
}
