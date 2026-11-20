<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

/**
 * Course access (ADR-0007): a course is available to companies, and learners
 * of those companies get access only when enrolled one by one.
 */
class CoursePolicy
{
    /**
     * Open the course content: enrolled learners of a company the published
     * course is available to; admins and superadmins may preview what they manage.
     */
    public function view(User $user, Course $course): bool
    {
        if ($user->isLearner()) {
            return $course->is_published
                && $course->isAvailableTo($user->company_id)
                && $course->learners()->whereKey($user->id)->exists();
        }

        return $this->manage($user, $course);
    }

    /**
     * See the course in the management screens and enrol learners: every
     * course for a superadmin, the courses available to the own company for an admin.
     */
    public function manage(User $user, Course $course): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isAdmin() && $course->isAvailableTo($user->company_id);
    }

    /** Upload, publish, delete, and make available to companies: superadmins only. */
    public function administer(User $user, Course $course): bool
    {
        return $user->isSuperAdmin();
    }
}
