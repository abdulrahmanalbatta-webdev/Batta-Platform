<?php

namespace App\Actions\PlatformData;

/**
 * The tables of the platform's business data, children before parents (the order a wipe deletes them in).
 * The team, the settings, the activity log and Laravel's own tables are not in it.
 */
final class PlatformTables
{
    public const BUSINESS = [
        'lesson_completions',
        'enrollments',
        'reviews',
        'comments',
        'conversation_messages',
        'conversations',
        'orders',
        'coupons',
        'students',
        'subscribers',
        'leads',
        'lessons',
        'course_modules',
        'courses',
        'workshops',
        'articles',
        'tools',
        'tool_categories',
    ];
}
