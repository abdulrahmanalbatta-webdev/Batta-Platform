<?php

namespace App\Actions\PlatformData;

/**
 * The tables of the platform's business data, children before parents (the order a wipe deletes them in).
 * The team, the settings, the activity log and Laravel's own tables are not in it.
 */
final class PlatformTables
{
    public const BUSINESS = [
        'enrollments',
        'workshop_registrations',
        'reviews',
        'comments',
        'conversation_messages',
        'conversations',
        'students',
        'leads',
        'learning_paths',
        'courses',
        'workshops',
        'articles',
        'tools',
        'tool_categories',
    ];
}
