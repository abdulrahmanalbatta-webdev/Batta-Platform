<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::view('/login', 'auth.login')->name('login');

// Dashboard screens (frontend only for now: data comes from public/dashboard/js/data.js)
Route::prefix('dashboard')->group(function () {
    Route::view('/', 'dashboard.index')->name('dashboard');
    Route::view('/analytics', 'analytics.index')->name('analytics');

    // content
    Route::view('/courses', 'courses.index')->name('courses.index');
    Route::view('/courses/create', 'courses.form')->name('courses.create');
    Route::view('/courses/{id}/edit', 'courses.form')->name('courses.edit');

    Route::view('/workshops', 'workshops.index')->name('workshops.index');

    Route::view('/articles', 'articles.index')->name('articles.index');
    Route::view('/articles/create', 'articles.form')->name('articles.create');
    Route::view('/articles/{id}/edit', 'articles.form')->name('articles.edit');

    Route::view('/tools', 'tools.index')->name('tools.index');

    // sales
    Route::view('/orders', 'orders.index')->name('orders.index');
    Route::view('/coupons', 'coupons.index')->name('coupons.index');
    Route::view('/leads', 'leads.index')->name('leads.index');

    // community
    Route::view('/students', 'students.index')->name('students.index');
    Route::view('/messages', 'messages.index')->name('messages.index');
    Route::view('/reviews', 'reviews.index')->name('reviews.index');

    // system
    Route::view('/settings', 'settings.index')->name('settings.index');
    Route::view('/profile', 'profile.index')->name('profile');
});
