<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property int|null $company_id
 * @property int|null $created_by_user_id
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Company|null $company
 * @property-read User|null $createdBy
 */
#[Fillable(['name', 'email', 'password', 'role', 'company_id', 'created_by_user_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function isLearner(): bool
    {
        return $this->role === UserRole::Learner;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    /** Admins and superadmins may use the administration screens. */
    public function canAdminister(): bool
    {
        return $this->isAdmin() || $this->isSuperAdmin();
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'created_by_user_id');
    }

    /** @return HasMany<User, $this> */
    public function createdUsers(): HasMany
    {
        return $this->hasMany(self::class, 'created_by_user_id');
    }

    /**
     * Courses the learner is enrolled in (ADR-0007).
     *
     * @return BelongsToMany<Course, $this, Enrollment>
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class)
            ->using(Enrollment::class)
            ->withPivot('enrolled_by_user_id')
            ->withTimestamps();
    }

    /** @return HasMany<Attempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /**
     * Courses the learner can open right now: enrolled, published, and
     * still available to the learner's company.
     *
     * @return BelongsToMany<Course, $this, Enrollment>
     */
    public function accessibleCourses(): BelongsToMany
    {
        return $this->courses()
            ->where('courses.is_published', true)
            ->whereHas('companies', fn ($query) => $query->whereKey($this->company_id ?? 0));
    }

    /**
     * Learners the given user may manage: everyone for a superadmin,
     * the own company's learners for an admin, nobody for a learner.
     *
     * @param  Builder<User>  $query
     */
    public function scopeManageableLearnersFor(Builder $query, User $actor): void
    {
        $query->where('role', UserRole::Learner);

        if ($actor->isSuperAdmin()) {
            return;
        }

        if ($actor->isAdmin() && $actor->company_id !== null) {
            $query->where('company_id', $actor->company_id);

            return;
        }

        $query->whereRaw('1 = 0');
    }
}
