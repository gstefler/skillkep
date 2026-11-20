<?php

namespace Tests\Feature\Courses;

use App\Enums\VideoStatus;
use App\Jobs\TranscodeVideo;
use App\Models\Company;
use App\Models\Course;
use App\Models\Material;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** ADR-0004: encrypted HLS, playlist and key only after the access check. */
class VideoDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('hls');
    }

    private function videoFor(Course $course, VideoStatus $status = VideoStatus::Pending): Material
    {
        $module = Module::factory()->for($course)->create(['order' => 1]);
        $material = Material::factory()->video($status)->for($module)->create();
        $material->update(['path' => "courses/{$course->id}/materials/{$material->id}/source.mp4"]);

        return $material;
    }

    public function test_a_video_is_transcoded_to_encrypted_hls(): void
    {
        if (! Process::run(['which', 'ffmpeg'])->successful()) {
            $this->markTestSkipped('ffmpeg is not installed.');
        }

        $material = $this->videoFor(Course::factory()->create());
        $source = Storage::disk('local')->path($material->path);
        @mkdir(dirname($source), 0755, true);

        Process::run([
            'ffmpeg', '-loglevel', 'error', '-f', 'lavfi', '-i', 'testsrc=duration=3:size=160x120:rate=10',
            '-f', 'lavfi', '-i', 'sine=duration=3', '-shortest', '-c:v', 'libx264', '-c:a', 'aac', $source,
        ])->throw();

        (new TranscodeVideo($material))->handle();

        $material->refresh();
        $this->assertSame(VideoStatus::Ready, $material->video_status);
        $this->assertSame(3, $material->duration_seconds);
        $this->assertSame(16, strlen((string) Storage::disk('local')->get($material->keyPath())));

        $playlist = (string) Storage::disk('local')->get($material->playlistPath());
        $this->assertStringContainsString('#EXT-X-KEY:METHOD=AES-128,URI="'.TranscodeVideo::KEY_URI_PLACEHOLDER.'"', $playlist);
        $this->assertNotEmpty(Storage::disk('hls')->files($material->hls_directory));
    }

    public function test_a_failed_transcode_is_recorded(): void
    {
        $material = $this->videoFor(Course::factory()->create());

        try {
            (new TranscodeVideo($material))->handle();
        } catch (\Throwable) {
            // The job rethrows so the queue records the failure.
        }

        $this->assertSame(VideoStatus::Failed, $material->fresh()->video_status);
        $this->assertNotNull($material->fresh()->video_error);
    }

    public function test_an_enrolled_learner_gets_the_rewritten_playlist_and_the_key(): void
    {
        $learner = User::factory()->create(['company_id' => Company::factory()]);
        $course = Course::factory()->enrolling($learner)->create();
        $material = $this->videoFor($course, VideoStatus::Ready);

        Storage::disk('local')->put($material->keyPath(), str_repeat('k', 16));
        Storage::disk('local')->put($material->playlistPath(), implode("\n", [
            '#EXTM3U',
            '#EXT-X-KEY:METHOD=AES-128,URI="'.TranscodeVideo::KEY_URI_PLACEHOLDER.'",IV=0x00',
            '#EXTINF:6.0,',
            '/var/www/storage/app/hls/abc/00000.ts',
            '#EXT-X-ENDLIST',
        ]));

        $playlist = $this->actingAs($learner)->get(route('materials.playlist', $material))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.apple.mpegurl')
            ->getContent();

        $this->assertStringContainsString('URI="'.route('materials.key', $material).'"', (string) $playlist);
        $this->assertStringContainsString(Storage::disk('hls')->url($material->hls_directory.'/00000.ts'), (string) $playlist);

        $this->assertSame(str_repeat('k', 16), $this->get(route('materials.key', $material))->assertOk()->getContent());
    }

    public function test_the_playlist_and_key_are_refused_without_enrolment(): void
    {
        $company = Company::factory()->create();
        $outsider = User::factory()->create(['company_id' => $company->id]);
        $course = Course::factory()->availableTo($company)->create();
        $material = $this->videoFor($course, VideoStatus::Ready);

        $this->actingAs($outsider)->get(route('materials.playlist', $material))->assertForbidden();
        $this->actingAs($outsider)->get(route('materials.key', $material))->assertForbidden();
    }

    public function test_a_video_that_is_not_ready_is_not_served(): void
    {
        $learner = User::factory()->create(['company_id' => Company::factory()]);
        $material = $this->videoFor(Course::factory()->enrolling($learner)->create());

        $this->actingAs($learner)->get(route('materials.playlist', $material))->assertNotFound();
    }

    public function test_pdfs_are_streamed_only_to_enrolled_learners(): void
    {
        $learner = User::factory()->create(['company_id' => Company::factory()]);
        $course = Course::factory()->enrolling($learner)->create();
        $module = Module::factory()->for($course)->create(['order' => 1]);
        $pdf = Material::factory()->for($module)->create(['path' => 'courses/1/materials/1/document.pdf', 'title' => 'Jegyzet']);
        Storage::disk('local')->put($pdf->path, '%PDF-1.4');

        $this->actingAs($learner)->get(route('materials.pdf', $pdf))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->actingAs(User::factory()->create(['company_id' => $learner->company_id]))
            ->get(route('materials.pdf', $pdf))
            ->assertForbidden();
    }

    public function test_deleting_a_course_removes_its_files(): void
    {
        $course = Course::factory()->create();
        $material = $this->videoFor($course, VideoStatus::Ready);
        Storage::disk('local')->put($material->path, 'video');
        Storage::disk('hls')->put($material->hls_directory.'/00000.ts', 'segment');

        $this->actingAs(User::factory()->superAdmin()->create())
            ->delete(route('manage.courses.destroy', $course))
            ->assertRedirect(route('manage.courses.index'));

        $this->assertModelMissing($course);
        Storage::disk('local')->assertMissing($material->path);
        Storage::disk('hls')->assertMissing($material->hls_directory.'/00000.ts');
    }
}
