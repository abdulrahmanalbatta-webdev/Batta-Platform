<?php

namespace App\Actions\PlatformData;

use App\Models\Activity;
use App\Models\ConversationMessage;
use App\Models\Student;
use App\Models\User;
use App\Support\DashboardSummary;
use App\Support\SalesReport;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes all of the platform's business data (courses, students, orders… see PlatformTables) with their
 * uploaded files. The team, the settings and the activity log stay; the wipe itself is logged.
 */
class WipePlatformData
{
    /**
     * @return array<string, int> rows deleted per table
     */
    public function handle(User $member): array
    {
        $deleted = DB::transaction(function (): array {
            $deleted = [];

            foreach (PlatformTables::BUSINESS as $table) {
                $deleted[$table] = DB::table($table)->delete();
            }

            // the students' site sign-ins and reset links
            DB::table('personal_access_tokens')->where('tokenable_type', (new Student)->getMorphClass())->delete();
            DB::table('student_password_reset_tokens')->delete();

            // bell entries pointing at what's gone
            DatabaseNotification::query()->delete();

            return $deleted;
        });

        Storage::disk('public')->deleteDirectory('courses');
        Storage::disk('public')->deleteDirectory('articles');
        Storage::disk('public')->deleteDirectory('tools');
        Storage::disk(ConversationMessage::ATTACHMENT_DISK)->deleteDirectory('conversations');

        Cache::forget(DashboardSummary::CACHE_KEY);
        foreach (SalesReport::PERIODS as $days) {
            Cache::forget("analytics.{$days}");
        }

        Activity::create(['user_id' => $member->id, 'action' => 'wiped', 'subject_name' => 'كل بيانات المنصة', 'properties' => ['rows' => $deleted]]);

        return $deleted;
    }
}
