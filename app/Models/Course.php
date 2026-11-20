<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property bool $is_published
 * @property bool $sequential_modules
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Enrollment|null $pivot Set when loaded through a user's enrolments.
 */
#[Fillable(['title', 'slug', 'description', 'is_published', 'sequential_modules', 'created_by_user_id'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sequential_modules' => 'boolean',
        ];
    }

    /** @return HasMany<Module, $this> */
    public function modules(): HasMany
    {
        return $this->hasMany(Module::class)->orderBy('order');
    }

    /** @return HasManyThrough<Material, Module, $this> */
    public function materials(): HasManyThrough
    {
        return $this->hasManyThrough(Material::class, Module::class);
    }

    /**
     * Companies the course is available to. Being available does not enrol anyone.
     *
     * @return BelongsToMany<Company, $this>
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withTimestamps();
    }

    /**
     * Learners enrolled one by one (ADR-0007).
     *
     * @return BelongsToMany<User, $this, Enrollment>
     */
    public function learners(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(Enrollment::class)
            ->withPivot('enrolled_by_user_id')
            ->withTimestamps();
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Courses the given user may manage: all for a superadmin, the ones
     * available to the own company for an admin, none for a learner.
     *
     * @param  Builder<Course>  $query
     */
    public function scopeManageableBy(Builder $query, User $actor): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($actor->isAdmin() && $actor->company_id !== null) {
            $query->whereHas('companies', fn (Builder $q) => $q->whereKey($actor->company_id));

            return;
        }

        $query->whereRaw('1 = 0');
    }

    /**
     * Learners who may be enrolled: learners of the companies the course is
     * available to, narrowed to the actor's own company for an admin.
     *
     * @return Builder<User>
     */
    public function enrollableLearnersFor(User $actor): Builder
    {
        return User::query()
            ->manageableLearnersFor($actor)
            ->where('role', UserRole::Learner)
            ->whereIn('company_id', $this->companies()->select('companies.id'));
    }

    public function isAvailableTo(?int $companyId): bool
    {
        return $companyId !== null && $this->companies()->whereKey($companyId)->exists();
    }

    /** Remove the stored PDFs, source videos, keys and HLS output. */
    public function deleteFiles(): void
    {
        foreach ($this->materials()->whereNotNull('hls_directory')->pluck('hls_directory') as $directory) {
            Storage::disk('hls')->deleteDirectory($directory);
        }

        Storage::disk('local')->deleteDirectory("courses/{$this->id}");
    }
}
