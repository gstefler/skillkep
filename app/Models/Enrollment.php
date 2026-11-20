<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * A learner's access to a course (course_user, ADR-0007).
 *
 * @property int $course_id
 * @property int $user_id
 * @property int|null $enrolled_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Enrollment extends Pivot
{
    protected $table = 'course_user';
}
