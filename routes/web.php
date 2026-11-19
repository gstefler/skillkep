<?php

use Illuminate\Support\Facades\Route;

// Every page requires login: "/" goes to the dashboard, and the auth
// middleware sends guests on to the login page.
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
