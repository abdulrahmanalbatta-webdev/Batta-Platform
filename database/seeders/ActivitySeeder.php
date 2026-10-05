<?php

namespace Database\Seeders;

use App\Enums\LeadStage;
use App\Enums\OrderStatus;
use App\Enums\ReviewStatus;
use App\Models\Activity;
use App\Models\Article;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Review;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * A recent activity log for the sample team (the seeders run without a signed-in member, so nothing is logged on its own). Runs last.
 */
class ActivitySeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::query()->where('email', 'admin@batta.dev')->firstOrFail();
        $editor = User::query()->where('email', 'sara@batta.dev')->firstOrFail();
        $support = User::query()->where('email', 'yousef@batta.dev')->firstOrFail();

        $wonLead = Lead::query()->where('stage', LeadStage::Won)->latest()->firstOrFail();
        $order = Order::query()->where('status', OrderStatus::Completed)->oldest('paid_at')->firstOrFail();
        $review = Review::query()->where('status', ReviewStatus::Published)->latest()->firstOrFail();
        $course = Course::query()->latest('updated_at')->firstOrFail();

        $this->log($owner, 'created', Coupon::query()->where('code', 'LAUNCH30')->firstOrFail(), now()->subDays(6));
        $this->log($editor, 'created', Article::query()->latest()->firstOrFail(), now()->subDays(4));
        $this->log($owner, 'status', $order, now()->subDays(3), $order->status->label());
        $this->log($support, 'updated', Student::query()->latest('last_active_at')->firstOrFail(), now()->subDays(2));
        $this->log($owner, 'status', $wonLead, now()->subDay(), $wonLead->stage->label());
        $this->log($support, 'status', $review, now()->subHours(20), $review->status->label());
        $this->log($editor, 'updated', $course, now()->subHours(3));
    }

    private function log(User $member, string $action, Model $subject, Carbon $at, ?string $state = null): void
    {
        Activity::create([
            'user_id' => $member->id,
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'subject_name' => $subject->activityName(),
            'properties' => $state ? ['state' => $state] : null,
            'created_at' => $at,
        ]);
    }
}
