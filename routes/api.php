<?php

use App\Http\Controllers\Api\AcceptedInvitationController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\ArticleCoverController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\CouponController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseCopyController;
use App\Http\Controllers\Api\CourseCoverController;
use App\Http\Controllers\Api\CourseStatusController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\OrderFailureController;
use App\Http\Controllers\Api\OrderInvoiceController;
use App\Http\Controllers\Api\OrderPaymentController;
use App\Http\Controllers\Api\OrderRefundController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentMessageController;
use App\Http\Controllers\Api\StudentStatusController;
use App\Http\Controllers\Api\TeamMemberController;
use App\Http\Controllers\Api\ToolCategoryController;
use App\Http\Controllers\Api\ToolController;
use App\Http\Controllers\Api\WorkshopController;
use App\Http\Controllers\Api\WorkshopRegistrationController;
use App\Http\Controllers\Api\WorkshopReminderController;
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

    // content: every member reads, members who may manage content write (Role::canManageContent)
    Route::get('/tools', [ToolController::class, 'index'])->name('tools.index');
    Route::get('/tool-categories', [ToolCategoryController::class, 'index'])->name('tool-categories.index');
    Route::get('/workshops', [WorkshopController::class, 'index'])->name('workshops.index');
    Route::get('/workshops/{workshop}/registrations', [WorkshopRegistrationController::class, 'index'])->name('workshops.registrations.index');
    Route::apiResource('articles', ArticleController::class)->only(['index', 'show']);
    Route::apiResource('courses', CourseController::class)->only(['index', 'show']);

    Route::middleware('can:manage-content')->group(function () {
        Route::apiResource('tools', ToolController::class)->only(['store', 'update', 'destroy']);
        Route::post('/tools/{tool}/move', [ToolController::class, 'move'])->name('tools.move');
        Route::apiResource('tool-categories', ToolCategoryController::class)->only(['store', 'update', 'destroy']);
        Route::post('/tool-categories/{tool_category}/move', [ToolCategoryController::class, 'move'])->name('tool-categories.move');
        Route::apiResource('workshops', WorkshopController::class)->only(['store', 'update', 'destroy']);
        Route::post('/workshops/{workshop}/reminders', [WorkshopReminderController::class, 'store'])->name('workshops.reminders.store');
        Route::apiResource('articles', ArticleController::class)->only(['store', 'update', 'destroy']);
        Route::post('/articles/{article}/cover', [ArticleCoverController::class, 'store'])->name('articles.cover.store');
        Route::apiResource('courses', CourseController::class)->only(['store', 'update', 'destroy']);
        Route::put('/courses/{course}/status', [CourseStatusController::class, 'update'])->name('courses.status.update');
        Route::post('/courses/{course}/copies', [CourseCopyController::class, 'store'])->name('courses.copies.store');
        Route::post('/courses/{course}/cover', [CourseCoverController::class, 'store'])->name('courses.cover.store');
    });

    // students: every member reads; owner, admin and support suspend and email (Role::canManageStudents)
    Route::apiResource('students', StudentController::class)->only(['index', 'show']);

    Route::middleware('can:manage-students')->group(function () {
        Route::put('/students/status', [StudentStatusController::class, 'update'])->name('students.status.update');
        Route::post('/students/messages', [StudentMessageController::class, 'store'])->name('students.messages.store');
    });

    // sales: every member reads; owner, admin and accountant confirm, refund and manage coupons (Role::canManageSales)
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/coupons', [CouponController::class, 'index'])->name('coupons.index');

    Route::middleware('can:manage-sales')->group(function () {
        Route::post('/orders/{order}/payment', [OrderPaymentController::class, 'store'])->name('orders.payment.store');
        Route::post('/orders/{order}/refund', [OrderRefundController::class, 'store'])->name('orders.refund.store');
        Route::post('/orders/{order}/failure', [OrderFailureController::class, 'store'])->name('orders.failure.store');
        Route::post('/orders/{order}/invoice', [OrderInvoiceController::class, 'store'])->name('orders.invoice.store');
        Route::apiResource('coupons', CouponController::class)->only(['store', 'update', 'destroy']);
    });
});
