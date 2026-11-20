<?php

namespace App\Http\Controllers\Manage;

use App\Enums\CourseUploadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Manage\StartCourseUploadRequest;
use App\Jobs\ProcessCourseUpload;
use App\Models\CourseUpload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resumable, chunked ZIP upload of a course (superadmin only).
 *
 * The browser asks to start (or resume) an upload of a file, sends the
 * missing chunks in parallel with raw PUT requests, and can pause at any
 * time. Once every chunk is on the server it completes the upload, which
 * queues the import; the page then polls the upload until it finishes.
 */
class CourseUploadController extends Controller
{
    public function create(Request $request): Response
    {
        $recentUploads = CourseUpload::query()
            ->with('course:id,title')
            ->whereBelongsTo($request->user())
            ->where('updated_at', '>=', now()->subDays(7))
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (CourseUpload $upload) => $this->payload($upload) + [
                'updated_at' => $upload->updated_at?->format('Y. m. d. H:i'),
            ]);

        return Inertia::render('manage/courses/Upload', [
            'recentUploads' => $recentUploads,
            'chunkSize' => CourseUpload::CHUNK_SIZE,
            'maxSize' => CourseUpload::MAX_SIZE,
        ]);
    }

    /**
     * Start a chunked upload, or resume the unfinished upload of the same file.
     */
    public function store(StartCourseUploadRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $fingerprint = CourseUpload::fingerprintFor($user, $data['filename'], (int) $data['size'], (int) $data['last_modified']);

        $upload = CourseUpload::query()
            ->whereBelongsTo($user)
            ->where('fingerprint', $fingerprint)
            ->whereIn('status', [CourseUploadStatus::Uploading, CourseUploadStatus::Processing])
            ->latest()
            ->first();

        if ($upload === null) {
            $upload = CourseUpload::query()->create([
                'user_id' => $user->getKey(),
                'fingerprint' => $fingerprint,
                'filename' => $data['filename'],
                'size' => $data['size'],
                'chunk_size' => CourseUpload::CHUNK_SIZE,
                'total_chunks' => (int) ceil($data['size'] / CourseUpload::CHUNK_SIZE),
                'pass_score' => $data['pass_score'],
                'lock_modules' => $data['lock_modules'],
                'status' => CourseUploadStatus::Uploading,
            ]);

            File::ensureDirectoryExists($upload->directory().'/chunks');

            return response()->json($this->payload($upload), 201);
        }

        if ($upload->status === CourseUploadStatus::Uploading) {
            $upload->update([
                'pass_score' => $data['pass_score'],
                'lock_modules' => $data['lock_modules'],
            ]);
        }

        return response()->json($this->payload($upload));
    }

    public function show(Request $request, CourseUpload $upload): JsonResponse
    {
        $this->authorizeOwner($request, $upload);

        return response()->json($this->payload($upload));
    }

    /**
     * Store one chunk of the archive. The request body is the raw chunk, and chunks are idempotent.
     */
    public function chunk(Request $request, CourseUpload $upload, int $index): JsonResponse
    {
        $this->authorizeOwner($request, $upload);

        if ($upload->status !== CourseUploadStatus::Uploading) {
            return response()->json(['message' => 'Ez a feltöltés már nem fogad újabb részeket.'], 409);
        }

        abort_if($index >= $upload->total_chunks, 404);

        $expectedLength = $upload->expectedChunkLength($index);

        File::ensureDirectoryExists($upload->directory().'/chunks');

        $input = $request->getContent(true);
        $archive = fopen($upload->archivePath(), 'c+b');

        if ($archive === false) {
            return response()->json(['message' => 'A feltöltés nem írható.'], 500);
        }

        fseek($archive, $index * $upload->chunk_size);
        $written = stream_copy_to_stream($input, $archive, $expectedLength + 1);
        fflush($archive);
        fclose($archive);

        if ($written !== $expectedLength) {
            @unlink($upload->chunkMarkerPath($index));

            return response()->json(['message' => "A(z) {$index}. rész mérete nem megfelelő."], 422);
        }

        touch($upload->chunkMarkerPath($index));
        $upload->touch();

        return response()->json(['index' => $index]);
    }

    /**
     * Verify that every chunk arrived and queue the archive for extraction and import.
     */
    public function complete(Request $request, CourseUpload $upload): JsonResponse
    {
        $this->authorizeOwner($request, $upload);

        if ($upload->status !== CourseUploadStatus::Uploading) {
            return response()->json($this->payload($upload));
        }

        if (! $upload->isFullyReceived()) {
            return response()->json([
                'message' => 'Néhány rész hiányzik. Folytassa a feltöltést a hiányzó részek elküldéséhez.',
                'received' => $upload->receivedChunks(),
            ], 422);
        }

        clearstatcache(true, $upload->archivePath());

        if (! $this->looksLikeZip($upload->archivePath()) || filesize($upload->archivePath()) !== $upload->size) {
            $upload->deleteFiles();
            $upload->delete();

            return response()->json(['message' => 'A feltöltött fájl nem érvényes ZIP-archívum.'], 422);
        }

        $upload->update([
            'status' => CourseUploadStatus::Processing,
            'stage' => 'queued',
            'stage_progress' => 0,
            'error' => null,
        ]);

        ProcessCourseUpload::dispatch($upload);

        return response()->json($this->payload($upload->refresh()));
    }

    public function destroy(Request $request, CourseUpload $upload): JsonResponse
    {
        $this->authorizeOwner($request, $upload);

        if ($upload->status === CourseUploadStatus::Processing) {
            return response()->json(['message' => 'A feltöltés feldolgozás alatt áll, ezért nem dobható el.'], 409);
        }

        $upload->deleteFiles();
        $upload->delete();

        return response()->json(null, 204);
    }

    private function authorizeOwner(Request $request, CourseUpload $upload): void
    {
        abort_unless($upload->user_id === $request->user()->getKey(), 404);
    }

    private function looksLikeZip(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $signature = fread($handle, 4);
        fclose($handle);

        return $signature === "PK\x03\x04" || $signature === "PK\x05\x06";
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CourseUpload $upload): array
    {
        $upload->loadMissing('course:id,title');

        return [
            'id' => $upload->getKey(),
            'filename' => $upload->filename,
            'size' => $upload->size,
            'chunk_size' => $upload->chunk_size,
            'total_chunks' => $upload->total_chunks,
            'received' => $upload->status === CourseUploadStatus::Uploading ? $upload->receivedChunks() : [],
            'status' => $upload->status->value,
            'status_label' => $upload->status->label(),
            'stage' => $upload->stage,
            'stage_progress' => $upload->stage_progress,
            'result' => $upload->result,
            'error' => $upload->error,
            'course' => $upload->course?->only(['id', 'title']),
        ];
    }
}
