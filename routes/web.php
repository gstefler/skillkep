<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\AttemptController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\Manage;
use App\Http\Controllers\MaterialController;
use App\Http\Controllers\ModuleController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

// Every page requires login: "/" goes to the dashboard, and the auth
// middleware sends guests on to the login page.
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Admins get stats here; learners are sent on to their course list.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Learner: enrolled courses, their modules, materials and tests. Access
    // is checked by CoursePolicy::view (ADR-0007).
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('courses/{course:slug}', [CourseController::class, 'show'])->name('courses.show');
    Route::get('courses/{course:slug}/modules/{module}', [ModuleController::class, 'show'])->name('courses.modules.show');

    Route::get('materials/{material}/pdf', [MaterialController::class, 'pdf'])->name('materials.pdf');
    Route::get('materials/{material}/playlist.m3u8', [MaterialController::class, 'playlist'])->name('materials.playlist');
    Route::get('materials/{material}/key', [MaterialController::class, 'key'])->name('materials.key');

    Route::post('tests/{test}/attempts', [AttemptController::class, 'store'])->name('attempts.store');
    Route::get('attempts/{attempt}', [AttemptController::class, 'show'])->name('attempts.show');
    Route::put('attempts/{attempt}/answers', [AttemptController::class, 'answer'])->name('attempts.answer');
    Route::post('attempts/{attempt}/finish', [AttemptController::class, 'finish'])->name('attempts.finish');
    Route::get('attempts/{attempt}/results', [AttemptController::class, 'results'])->name('attempts.results');

    // Admin and superadmin: learners of their company (all companies for a superadmin).
    Route::middleware('admin')->group(function () {
        Route::resource('learners', LearnerController::class)
            ->parameters(['learners' => 'user'])
            ->only(['index', 'create', 'store', 'show', 'destroy']);
        Route::post('users/{user}/password-reset', [PasswordResetController::class, 'store'])
            ->name('users.password-reset');

        // Courses: admins see the ones available to their company and enrol its learners.
        Route::prefix('manage')->name('manage.')->group(function () {
            Route::get('courses', [Manage\CourseController::class, 'index'])->name('courses.index');
            Route::get('courses/{course}', [Manage\CourseController::class, 'show'])
                ->whereNumber('course')
                ->name('courses.show');
            Route::put('courses/{course}/learners', [Manage\CourseAccessController::class, 'learners'])
                ->name('courses.learners.update');
            Route::post('courses/{course}/learners/{user}', [Manage\CourseAccessController::class, 'enrol'])
                ->name('courses.learners.store');
            Route::delete('courses/{course}/learners/{user}', [Manage\CourseAccessController::class, 'unenrol'])
                ->name('courses.learners.destroy');
        });
    });

    // Superadmin only: companies and company admins.
    Route::middleware('superadmin')->group(function () {
        Route::resource('companies', CompanyController::class);
        Route::resource('admins', AdminUserController::class)
            ->parameters(['admins' => 'user'])
            ->only(['index', 'create', 'store', 'destroy']);

        Route::prefix('manage')->name('manage.')->group(function () {
            // Resumable chunked ZIP upload (JSON endpoints used by the upload page).
            Route::get('courses/upload', [Manage\CourseUploadController::class, 'create'])->name('courses.uploads.create');
            Route::post('course-uploads', [Manage\CourseUploadController::class, 'store'])->name('courses.uploads.store');
            Route::get('course-uploads/{upload}', [Manage\CourseUploadController::class, 'show'])->name('courses.uploads.show');
            Route::put('course-uploads/{upload}/chunks/{index}', [Manage\CourseUploadController::class, 'chunk'])
                ->whereNumber('index')
                ->name('courses.uploads.chunk');
            Route::post('course-uploads/{upload}/complete', [Manage\CourseUploadController::class, 'complete'])->name('courses.uploads.complete');
            Route::delete('course-uploads/{upload}', [Manage\CourseUploadController::class, 'destroy'])->name('courses.uploads.destroy');

            Route::patch('courses/{course}', [Manage\CourseController::class, 'update'])->name('courses.update');
            Route::delete('courses/{course}', [Manage\CourseController::class, 'destroy'])->name('courses.destroy');
            Route::patch('courses/{course}/publish', [Manage\CourseController::class, 'publish'])->name('courses.publish');
            Route::patch('courses/{course}/sequential-modules', [Manage\CourseController::class, 'sequentialModules'])
                ->name('courses.sequential-modules');
            Route::put('courses/{course}/companies', [Manage\CourseAccessController::class, 'companies'])
                ->name('courses.companies.update');
            Route::post('materials/{material}/transcode', [Manage\MaterialController::class, 'transcode'])
                ->name('materials.transcode');
        });
    });
});

require __DIR__.'/settings.php';
