<?php

use App\Http\Controllers\AdminUserController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LearnerController;
use App\Http\Controllers\PasswordResetController;
use Illuminate\Support\Facades\Route;

// Every page requires login: "/" goes to the dashboard, and the auth
// middleware sends guests on to the login page.
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Admins get stats here; learners are sent on to their course list.
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    // Learner: the (still empty) course list.
    Route::get('courses', [CourseController::class, 'index'])->name('courses.index');

    // Admin and superadmin: learners of their company (all companies for a superadmin).
    Route::middleware('admin')->group(function () {
        Route::resource('learners', LearnerController::class)
            ->parameters(['learners' => 'user'])
            ->only(['index', 'create', 'store', 'show', 'destroy']);
        Route::post('users/{user}/password-reset', [PasswordResetController::class, 'store'])
            ->name('users.password-reset');
    });

    // Superadmin only: companies and company admins.
    Route::middleware('superadmin')->group(function () {
        Route::resource('companies', CompanyController::class);
        Route::resource('admins', AdminUserController::class)
            ->parameters(['admins' => 'user'])
            ->only(['index', 'create', 'store', 'destroy']);
    });
});

require __DIR__.'/settings.php';
