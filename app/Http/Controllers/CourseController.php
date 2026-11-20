<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    /**
     * The learner's course list. Courses do not exist yet, so it is always empty.
     */
    public function index(): Response
    {
        return Inertia::render('courses/Index', [
            'courses' => [],
        ]);
    }
}
