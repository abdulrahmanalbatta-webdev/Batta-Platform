<?php

use App\Http\Controllers\Api\AcceptedInvitationController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\TeamMemberController;
use Illuminate\Support\Facades\Route;

/*
| Dashboard JSON API, loaded by bootstrap/app.php under /dashboard/api/v1 with the "web"
| middleware: requests share the dashboard session and must send the CSRF token.
| The dashboard calls it through App.api in public/assets/dashboard/js/app.js.
|
| Sign-in, sign-out, password reset and password change come from Laravel Fortify under
| /dashboard/api/v1/auth (config/fortify.php), e.g. POST auth/login and PUT auth/user/password.
*/

Route::get('/status', StatusController::class)->name('status');

Route::post('/invitations/accept', [AcceptedInvitationController::class, 'store'])
    ->middleware(['guest', 'throttle:6,1'])
    ->name('invitations.accept');

Route::middleware('auth')->group(function () {
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [AvatarController::class, 'store'])->name('profile.avatar.store');

    Route::apiResource('team', TeamMemberController::class)
        ->except('show')
        ->parameters(['team' => 'member']);

    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');
});
