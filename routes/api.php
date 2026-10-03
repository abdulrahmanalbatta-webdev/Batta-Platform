<?php

use App\Http\Controllers\Api\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Api\Auth\NewPasswordController;
use App\Http\Controllers\Api\Auth\PasswordResetLinkController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\PasswordController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\TeamMemberController;
use Illuminate\Support\Facades\Route;

/*
| Dashboard JSON API, loaded by bootstrap/app.php under /dashboard/api/v1 with the "web"
| middleware: requests share the dashboard session and must send the CSRF token.
| The dashboard calls it through App.api in public/assets/dashboard/js/app.js.
*/

Route::get('/status', StatusController::class)->name('status');

Route::middleware('guest')->prefix('auth')->name('auth.')->group(function () {
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login');
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');
        Route::post('/reset-password', [NewPasswordController::class, 'store'])->name('password.store');
    });
});

Route::middleware('auth')->group(function () {
    Route::post('/auth/logout', [AuthenticatedSessionController::class, 'destroy'])->name('auth.logout');

    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [PasswordController::class, 'update'])->name('profile.password.update');
    Route::post('/profile/avatar', [AvatarController::class, 'store'])->name('profile.avatar.store');

    Route::apiResource('team', TeamMemberController::class)
        ->except('show')
        ->parameters(['team' => 'member']);

    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');
});
