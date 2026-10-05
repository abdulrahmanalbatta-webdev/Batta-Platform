<?php

use App\Http\Controllers\Site\AccountController;
use App\Http\Controllers\Site\AccountPasswordController;
use App\Http\Controllers\Site\ArticleCommentController;
use App\Http\Controllers\Site\ArticleController;
use App\Http\Controllers\Site\ContactMessageController;
use App\Http\Controllers\Site\ContentController;
use App\Http\Controllers\Site\CourseController;
use App\Http\Controllers\Site\CourseReviewController;
use App\Http\Controllers\Site\LessonCompletionController;
use App\Http\Controllers\Site\MyCourseController;
use App\Http\Controllers\Site\MyReviewController;
use App\Http\Controllers\Site\NewsletterController;
use App\Http\Controllers\Site\ProjectRequestController;
use App\Http\Controllers\Site\RegisteredStudentController;
use App\Http\Controllers\Site\SettingsController;
use App\Http\Controllers\Site\StatsController;
use App\Http\Controllers\Site\StudentNewPasswordController;
use App\Http\Controllers\Site\StudentPasswordResetLinkController;
use App\Http\Controllers\Site\StudentTokenController;
use App\Http\Controllers\Site\ToolController;
use App\Http\Controllers\Site\WorkshopController;
use Illuminate\Support\Facades\Route;

/*
| The public site's JSON API, loaded by bootstrap/app.php under /api/v1 with the stateless "api" middleware.
| Cross-origin calls are allowed from CORS_ALLOWED_ORIGINS (config/cors.php).
|
| Students sign in with POST auth/login and send the token back as "Authorization: Bearer <token>";
| there is no cookie or CSRF token. Everything else here is public.
*/

Route::get('/settings', SettingsController::class)->name('settings');
Route::get('/stats', StatsController::class)->name('stats');
Route::get('/content', ContentController::class)->name('content');

Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
Route::get('/courses/{slug}', [CourseController::class, 'show'])->name('courses.show');
Route::get('/courses/{slug}/reviews', [CourseReviewController::class, 'index'])->name('courses.reviews');
Route::get('/workshops', [WorkshopController::class, 'index'])->name('workshops.index');
Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::get('/articles/{slug}/comments', [ArticleCommentController::class, 'index'])->name('articles.comments.index');
Route::get('/tools', [ToolController::class, 'index'])->name('tools.index');
Route::post('/tools/{tool}/click', [ToolController::class, 'click'])->whereNumber('tool')->name('tools.click');

Route::middleware('throttle:site-forms')->group(function () {
    Route::post('/contact', [ContactMessageController::class, 'store'])->name('contact');
    Route::post('/project-requests', [ProjectRequestController::class, 'store'])->name('project-requests');
    Route::post('/newsletter', [NewsletterController::class, 'store'])->name('newsletter');
    Route::post('/auth/register', [RegisteredStudentController::class, 'store'])->name('auth.register');
    Route::post('/auth/forgot-password', [StudentPasswordResetLinkController::class, 'store'])->name('auth.forgot-password');
    Route::post('/auth/reset-password', [StudentNewPasswordController::class, 'store'])->name('auth.reset-password');
});

Route::post('/auth/login', [StudentTokenController::class, 'store'])->middleware('throttle:student-login')->name('auth.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [StudentTokenController::class, 'destroy'])->name('auth.logout');

    Route::get('/me', [AccountController::class, 'show'])->name('me.show');
    Route::put('/me', [AccountController::class, 'update'])->name('me.update');
    Route::put('/me/password', [AccountPasswordController::class, 'update'])->name('me.password');

    Route::get('/me/courses', [MyCourseController::class, 'index'])->name('me.courses.index');
    Route::get('/me/courses/{slug}', [MyCourseController::class, 'show'])->name('me.courses.show');
    Route::put('/me/courses/{slug}/review', [MyReviewController::class, 'update'])->name('me.courses.review');
    Route::post('/articles/{slug}/comments', [ArticleCommentController::class, 'store'])->middleware('throttle:site-forms')->name('articles.comments.store');
    Route::post('/me/lessons/{lesson}/completion', [LessonCompletionController::class, 'store'])->name('me.lessons.complete');
    Route::delete('/me/lessons/{lesson}/completion', [LessonCompletionController::class, 'destroy'])->name('me.lessons.uncomplete');
});
