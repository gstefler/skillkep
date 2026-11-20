<?php

namespace App\Models;

use App\Enums\MaterialType;
use App\Enums\VideoStatus;
use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $module_id
 * @property string $title
 * @property MaterialType $type
 * @property string $path Source file on the local (private) disk.
 * @property int $size
 * @property int $order
 * @property VideoStatus|null $video_status
 * @property string|null $hls_directory Directory of the encrypted segments on the hls disk.
 * @property int|null $duration_seconds
 * @property string|null $video_error
 * @property-read Module $module
 */
#[Fillable(['module_id', 'title', 'type', 'path', 'size', 'order', 'video_status', 'hls_directory', 'duration_seconds', 'video_error'])]
class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MaterialType::class,
            'video_status' => VideoStatus::class,
            'size' => 'integer',
            'order' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    /** @return BelongsTo<Module, $this> */
    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function isPdf(): bool
    {
        return $this->type === MaterialType::Pdf;
    }

    public function isVideo(): bool
    {
        return $this->type === MaterialType::Video;
    }

    public function isPlayable(): bool
    {
        return $this->isVideo() && $this->video_status === VideoStatus::Ready && $this->hls_directory !== null;
    }

    /** Private directory (local disk) for the key and the playlist template. */
    public function videoDirectory(): string
    {
        return dirname($this->path).'/hls';
    }

    public function keyPath(): string
    {
        return $this->videoDirectory().'/video.key';
    }

    public function playlistPath(): string
    {
        return $this->videoDirectory().'/index.m3u8';
    }
}
