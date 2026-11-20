<?php

namespace App\Models;

use App\Enums\CourseUploadStatus;
use Database\Factories\CourseUploadFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A resumable, chunked ZIP upload. The client sends fixed-size chunks in any
 * order (and again after a pause); each chunk is written in place into the
 * archive and marked as received. Once complete, a queued job imports it.
 *
 * @property string $id
 * @property int $user_id
 * @property string $fingerprint
 * @property string $filename
 * @property int $size
 * @property int $chunk_size
 * @property int $total_chunks
 * @property int $pass_score
 * @property bool $lock_modules
 * @property CourseUploadStatus $status
 * @property string|null $stage
 * @property int $stage_progress
 * @property int|null $course_id
 * @property array<string, int>|null $result
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Course|null $course
 */
#[Fillable([
    'user_id', 'fingerprint', 'filename', 'size', 'chunk_size', 'total_chunks', 'pass_score',
    'lock_modules', 'status', 'stage', 'stage_progress', 'course_id', 'result', 'error',
])]
class CourseUpload extends Model
{
    /** @use HasFactory<CourseUploadFactory> */
    use HasFactory, HasUlids;

    public const int CHUNK_SIZE = 8 * 1024 * 1024;

    public const int MAX_SIZE = 8 * 1024 * 1024 * 1024;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'chunk_size' => 'integer',
            'total_chunks' => 'integer',
            'pass_score' => 'integer',
            'lock_modules' => 'boolean',
            'status' => CourseUploadStatus::class,
            'stage_progress' => 'integer',
            'result' => 'array',
        ];
    }

    public static function fingerprintFor(User $user, string $filename, int $size, int $lastModified): string
    {
        return hash('sha256', implode('|', [$user->getKey(), $filename, $size, $lastModified]));
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function directory(): string
    {
        return Storage::disk('local')->path("course-uploads/{$this->getKey()}");
    }

    public function archivePath(): string
    {
        return $this->directory().'/archive.zip';
    }

    public function chunkMarkerPath(int $index): string
    {
        return $this->directory().'/chunks/'.$index;
    }

    public function expectedChunkLength(int $index): int
    {
        if ($index === $this->total_chunks - 1) {
            return $this->size - $index * $this->chunk_size;
        }

        return $this->chunk_size;
    }

    /**
     * Indices of the chunks that have been fully written to the archive.
     *
     * @return list<int>
     */
    public function receivedChunks(): array
    {
        $indices = [];

        foreach (glob($this->directory().'/chunks/*') ?: [] as $path) {
            $index = basename($path);

            if (ctype_digit($index) && (int) $index < $this->total_chunks) {
                $indices[] = (int) $index;
            }
        }

        sort($indices);

        return $indices;
    }

    public function isFullyReceived(): bool
    {
        return count($this->receivedChunks()) === $this->total_chunks;
    }

    public function deleteFiles(): void
    {
        Storage::disk('local')->deleteDirectory("course-uploads/{$this->getKey()}");
    }
}
