<?php

namespace App\Models;

use Database\Factories\AttemptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $test_id
 * @property Carbon $started_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $time_limit_expires_at
 * @property int|null $score
 * @property bool|null $is_passed
 * @property-read Test $test
 */
#[Fillable(['user_id', 'test_id', 'started_at', 'submitted_at', 'time_limit_expires_at', 'score', 'is_passed'])]
class Attempt extends Model
{
    /** @use HasFactory<AttemptFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'time_limit_expires_at' => 'datetime',
            'score' => 'integer',
            'is_passed' => 'boolean',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Test, $this> */
    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }

    /** @return HasMany<AttemptAnswer, $this> */
    public function answers(): HasMany
    {
        return $this->hasMany(AttemptAnswer::class);
    }

    public function isExpired(): bool
    {
        return $this->time_limit_expires_at !== null && now()->isAfter($this->time_limit_expires_at);
    }

    public function isInProgress(): bool
    {
        return $this->submitted_at === null && ! $this->isExpired();
    }
}
