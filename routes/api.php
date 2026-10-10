<?php

use App\Http\Controllers\Api\AcceptedInvitationController;
use App\Http\Controllers\Api\ActivityController;
use App\Http\Controllers\Api\AnalyticsConnectionController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\ArticleCoverController;
use App\Http\Controllers\Api\AvatarController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CommentReplyController;
use App\Http\Controllers\Api\CommentStatusController;
use App\Http\Controllers\Api\ConversationAttachmentController;
use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\ConversationMessageController;
use App\Http\Controllers\Api\ConversationReadController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\CourseCopyController;
use App\Http\Controllers\Api\CourseCoverController;
use App\Http\Controllers\Api\CourseStatusController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DataExportController;
use App\Http\Controllers\Api\DataWipeController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\LearningPathController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\NotificationPreferenceController;
use App\Http\Controllers\Api\NotificationReadController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ReviewReplyController;
use App\Http\Controllers\Api\ReviewStatusController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\SettingSecretController;
use App\Http\Controllers\Api\SiteContentController;
use App\Http\Controllers\Api\SiteImageController;
use App\Http\Controllers\Api\SitePhotoController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\StudentMessageController;
use App\Http\Controllers\Api\StudentStatusController;
use App\Http\Controllers\Api\SubscriberController;
use App\Http\Controllers\Api\TeamMemberController;
use App\Http\Controllers\Api\TestEmailController;
use App\Http\Controllers\Api\ToolCategoryController;
use App\Http\Controllers\Api\ToolController;
use App\Http\Controllers\Api\ToolLogoController;
use App\Http\Controllers\Api\TrafficController;
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
    ->middleware(['guest', 'throttle:6,1,invitations'])
    ->name('invitations.accept');

Route::middleware('auth')->group(function () {
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [AvatarController::class, 'store'])->name('profile.avatar.store');

    Route::apiResource('team', TeamMemberController::class)
        ->except('show')
        ->parameters(['team' => 'member']);

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/analytics', AnalyticsController::class)->name('analytics');
    Route::get('/analytics/traffic', TrafficController::class)->middleware('throttle:30,1,traffic')->name('analytics.traffic');
    Route::get('/activity', [ActivityController::class, 'index'])->name('activity.index');
    Route::get('/search', SearchController::class)->name('search');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read', [NotificationReadController::class, 'storeAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationReadController::class, 'store'])->name('notifications.read');
    Route::put('/notification-preferences', [NotificationPreferenceController::class, 'update'])->name('notification-preferences.update');

    Route::get('/sessions', [SessionController::class, 'index'])->name('sessions.index');
    Route::delete('/sessions/{session}', [SessionController::class, 'destroy'])->name('sessions.destroy');

    // content: every member reads, members who may manage content write (Role::canManageContent)
    Route::get('/tools', [ToolController::class, 'index'])->name('tools.index');
    Route::get('/tool-categories', [ToolCategoryController::class, 'index'])->name('tool-categories.index');
    Route::get('/workshops', [WorkshopController::class, 'index'])->name('workshops.index');
    Route::get('/workshops/{workshop}/registrations', [WorkshopRegistrationController::class, 'index'])->name('workshops.registrations.index');
    Route::apiResource('articles', ArticleController::class)->only(['index', 'show']);
    Route::apiResource('courses', CourseController::class)->only(['index', 'show']);
    Route::apiResource('learning-paths', LearningPathController::class)->only(['index', 'show']);

    Route::middleware('can:manage-content')->group(function () {
        Route::apiResource('tools', ToolController::class)->only(['store', 'update', 'destroy']);
        Route::post('/tools/{tool}/move', [ToolController::class, 'move'])->name('tools.move');
        Route::post('/tools/{tool}/logo', [ToolLogoController::class, 'store'])->name('tools.logo.store');
        Route::delete('/tools/{tool}/logo', [ToolLogoController::class, 'destroy'])->name('tools.logo.destroy');
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
        Route::apiResource('learning-paths', LearningPathController::class)->only(['store', 'update', 'destroy']);
        Route::post('/learning-paths/{learning_path}/move', [LearningPathController::class, 'move'])->name('learning-paths.move');
    });

    // students: every member reads; owner, admin and support suspend and email (Role::canManageStudents)
    Route::apiResource('students', StudentController::class)->only(['index', 'show']);

    Route::get('/subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');

    Route::middleware('can:manage-students')->group(function () {
        Route::delete('/subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');
        Route::put('/students/status', [StudentStatusController::class, 'update'])->name('students.status.update');
        Route::post('/students/messages', [StudentMessageController::class, 'store'])->name('students.messages.store');
    });

    // messages: every member reads; owner, admin and support reply, mark read and delete (Role::canAnswerMessages)
    Route::apiResource('conversations', ConversationController::class)->only(['index', 'show']);
    Route::get('/conversations/{conversation}/messages/{message}/attachment', [ConversationAttachmentController::class, 'show'])
        ->scopeBindings()
        ->name('conversations.messages.attachment');

    Route::middleware('can:answer-messages')->group(function () {
        Route::apiResource('conversations', ConversationController::class)->only(['store', 'destroy']);
        Route::post('/conversations/{conversation}/read', [ConversationReadController::class, 'store'])->name('conversations.read.store');
        Route::delete('/conversations/{conversation}/read', [ConversationReadController::class, 'destroy'])->name('conversations.read.destroy');
        Route::post('/conversations/{conversation}/messages', [ConversationMessageController::class, 'store'])
            ->middleware('throttle:30,1,conversation-replies')
            ->name('conversations.messages.store');
    });

    // reviews: every member reads; owner, admin, editor and support moderate (Role::canModerateReviews)
    Route::get('/reviews', [ReviewController::class, 'index'])->name('reviews.index');
    Route::get('/comments', [CommentController::class, 'index'])->name('comments.index');

    Route::middleware('can:moderate-reviews')->group(function () {
        Route::put('/reviews/{review}/status', [ReviewStatusController::class, 'update'])->name('reviews.status.update');
        Route::put('/reviews/{review}/reply', [ReviewReplyController::class, 'update'])->name('reviews.reply.update');
        Route::delete('/reviews/{review}/reply', [ReviewReplyController::class, 'destroy'])->name('reviews.reply.destroy');
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');

        // comments on articles, courses and workshops: moderated by the same roles as reviews
        Route::put('/comments/{comment}/status', [CommentStatusController::class, 'update'])->name('comments.status.update');
        Route::put('/comments/{comment}/reply', [CommentReplyController::class, 'update'])->name('comments.reply.update');
        Route::delete('/comments/{comment}/reply', [CommentReplyController::class, 'destroy'])->name('comments.reply.destroy');
        Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    });

    // project requests: every member reads; owner and admin manage the board (Role::canManageLeads)
    Route::get('/leads', [LeadController::class, 'index'])->name('leads.index');

    Route::middleware('can:manage-leads')->group(function () {
        Route::apiResource('leads', LeadController::class)->only(['store', 'update', 'destroy']);
    });

    // the public site's own content (announcement, about, services, case studies…): members read, content editors change it
    Route::get('/site-content', [SiteContentController::class, 'show'])->name('site-content.show');
    Route::middleware('can:manage-content')->group(function () {
        Route::put('/site-content/{key}', [SiteContentController::class, 'update'])->name('site-content.update');
        Route::delete('/site-content/{key}', [SiteContentController::class, 'destroy'])->name('site-content.destroy');
        Route::post('/site-photo', [SitePhotoController::class, 'store'])->name('site-photo.store');
        Route::post('/site-images', [SiteImageController::class, 'store'])->name('site-images.store');
        Route::delete('/site-photo', [SitePhotoController::class, 'destroy'])->name('site-photo.destroy');
    });

    // platform settings: every member reads (secrets stay masked); owner and admin change them (Role::canManageSettings)
    Route::get('/settings', [SettingController::class, 'show'])->name('settings.show');

    Route::middleware('can:manage-settings')->group(function () {
        Route::put('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/analytics-test', [AnalyticsConnectionController::class, 'store'])->middleware('throttle:5,1,analytics-test')->name('settings.analytics-test');
    });

    // where payments and mail go: owner only, like the settings marked 'owner' in PlatformSettings
    Route::middleware('can:manage-platform-data')->group(function () {
        Route::delete('/settings/secrets/{key}', [SettingSecretController::class, 'destroy'])->name('settings.secrets.destroy');
        Route::post('/settings/test-email', [TestEmailController::class, 'store'])->middleware('throttle:5,1,test-email')->name('settings.test-email');
    });

    // all of the platform's data: owner only (Role::canManagePlatformData)
    Route::middleware('can:manage-platform-data')->group(function () {
        Route::get('/data-exports', [DataExportController::class, 'index'])->name('data-exports.index');
        Route::post('/data-exports', [DataExportController::class, 'store'])->middleware('throttle:3,10,data-exports')->name('data-exports.store');
        Route::get('/data-exports/{file}', [DataExportController::class, 'show'])->name('data-exports.show');
        Route::delete('/data-exports/{file}', [DataExportController::class, 'destroy'])->name('data-exports.destroy');
        Route::post('/data-wipe', [DataWipeController::class, 'store'])->middleware('throttle:3,10,data-wipe')->name('data-wipe');
    });
});
