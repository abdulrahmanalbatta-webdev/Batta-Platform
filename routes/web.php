<?php

use App\Http\Controllers\NewsletterUnsubscribeController;
use App\Models\Article;
use App\Models\Course;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

// the unsubscribe link in newsletter emails (signed, so nobody can unsubscribe someone else)
Route::middleware(['signed:relative', 'throttle:20,1,unsubscribe'])->group(function () {
    Route::get('/newsletter/unsubscribe', [NewsletterUnsubscribeController::class, 'show'])->name('newsletter.unsubscribe');
    Route::post('/newsletter/unsubscribe', [NewsletterUnsubscribeController::class, 'store'])->name('newsletter.unsubscribe.store');
});

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    // link from the reset-password and team-invitation emails (?invite=1)
    Route::view('/reset-password/{token}', 'auth.reset-password')->name('password.reset');
});

// Dashboard screens: the pages call the JSON API in routes/api.php; pages not built yet still read sample data from public/assets/dashboard/js/data.js
Route::middleware('auth')->prefix('dashboard')->group(function () {
    Route::view('/', 'dashboard.index')->name('dashboard');
    Route::view('/analytics', 'analytics.index')->name('analytics');

    // content
    Route::view('/courses', 'courses.index')->name('courses.index');
    // create and edit pages are for members who may manage content; edit pages resolve the record first,
    // so an unknown id is a 404 instead of an empty "new" form
    Route::view('/courses/create', 'courses.form')->middleware('can:manage-content')->name('courses.create');
    Route::get('/courses/{course}/edit', fn (Course $course) => view('courses.form', ['id' => $course->id]))
        ->middleware('can:manage-content')->name('courses.edit');

    Route::view('/workshops', 'workshops.index')->name('workshops.index');

    Route::view('/articles', 'articles.index')->name('articles.index');
    Route::view('/articles/create', 'articles.form')->middleware('can:manage-content')->name('articles.create');
    Route::get('/articles/{article}/edit', fn (Article $article) => view('articles.form', ['id' => $article->id]))
        ->middleware('can:manage-content')->name('articles.edit');

    Route::view('/tools', 'tools.index')->name('tools.index');
    Route::view('/site-content', 'site-content.index')->name('site-content.index');

    // sales
    Route::view('/orders', 'orders.index')->name('orders.index');
    Route::view('/coupons', 'coupons.index')->name('coupons.index');
    Route::view('/leads', 'leads.index')->name('leads.index');

    // community
    Route::view('/students', 'students.index')->name('students.index');
    Route::view('/subscribers', 'subscribers.index')->name('subscribers.index');
    Route::view('/messages', 'messages.index')->name('messages.index');
    Route::view('/reviews', 'reviews.index')->name('reviews.index');

    // system
    Route::view('/settings', 'settings.index')->name('settings.index');
    Route::view('/profile', 'profile.index')->name('profile');
});
