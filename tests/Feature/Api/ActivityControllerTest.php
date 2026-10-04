<?php

namespace Tests\Feature\Api;

use App\Models\Activity;
use App\Models\Coupon;
use App\Models\Course;
use App\Models\Order;
use App\Models\Student;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_logs_what_a_member_creates_changes_and_deletes(): void
    {
        $member = User::factory()->owner()->create(['name' => 'سارة']);
        $this->actingAs($member);

        $course = Course::factory()->create(['title' => 'Next.js']);
        $course->update(['price' => 99]);
        $course->update(['status' => 'published']);
        $course->delete();

        $response = $this->getJson(route('api.activity.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.description', 'حذف الدورة «Next.js»')
            ->assertJsonPath('data.1.description', 'غيّر حالة الدورة «Next.js» إلى «منشورة»')
            ->assertJsonPath('data.1.action', 'status')
            ->assertJsonPath('data.2.description', 'عدّل الدورة «Next.js»')
            ->assertJsonPath('data.3.description', 'أضاف الدورة «Next.js»')
            ->assertJsonPath('data.3.user', 'سارة');
    }

    public function test_changes_without_a_member_are_not_logged(): void
    {
        $course = Course::factory()->create();
        $course->update(['status' => 'published']);

        $this->assertSame(0, Activity::count());
    }

    public function test_bookkeeping_changes_make_no_entry(): void
    {
        $tool = Tool::factory()->create();
        $coupon = Coupon::factory()->create();
        $this->actingAs(User::factory()->create());

        $tool->forceFill(['position' => 9])->save();
        $coupon->increment('times_used');

        $this->assertSame(0, Activity::count());
    }

    public function test_order_payment_is_logged_with_its_number_and_new_state(): void
    {
        $order = Order::factory()->pending()->create();

        $this->actingAs(User::factory()->owner()->create())->postJson(route('api.orders.payment.store', $order))->assertOk();

        $this->assertSame('غيّر حالة الطلب «'.$order->number().'» إلى «مكتمل»', $this->getJson(route('api.activity.index'))->json('data.0.description'));
    }

    public function test_suspending_several_students_logs_each_one(): void
    {
        $students = Student::factory()->count(2)->create();

        $this->actingAs(User::factory()->owner()->create())
            ->putJson(route('api.students.status.update'), ['ids' => $students->modelKeys(), 'status' => 'suspended'])
            ->assertJsonPath('updated', 2);

        $this->assertSame(2, Activity::query()->where('action', 'status')->where('properties->state', 'موقوف')->count());
    }

    public function test_entry_keeps_reading_after_its_member_is_removed(): void
    {
        $owner = User::factory()->owner()->create();
        $member = User::factory()->create(['name' => 'يوسف']);
        $this->actingAs($member);
        Course::factory()->create(['title' => 'Git']);
        $this->actingAs($owner);
        $member->delete();

        $response = $this->getJson(route('api.activity.index'));

        $response->assertJsonPath('data.0.description', 'حذف العضو «يوسف»')
            ->assertJsonPath('data.1.user', 'عضو محذوف')
            ->assertJsonPath('data.1.description', 'أضاف الدورة «Git»');
    }

    public function test_mine_keeps_only_the_signed_in_members_entries(): void
    {
        $other = User::factory()->create();
        $me = User::factory()->create();
        $this->actingAs($other);
        Course::factory()->create(['title' => 'Git']);
        $this->actingAs($me);
        Course::factory()->create(['title' => 'Next.js']);

        $response = $this->getJson(route('api.activity.index', ['mine' => 1]));

        $response->assertJsonCount(1, 'data')->assertJsonPath('data.0.description', 'أضاف الدورة «Next.js»');
    }

    public function test_pages_with_a_cursor_and_a_limit(): void
    {
        $this->actingAs(User::factory()->create());
        Course::factory()->count(3)->create();

        $first = $this->getJson(route('api.activity.index', ['limit' => 2]));
        $second = $this->getJson(route('api.activity.index', ['limit' => 2, 'cursor' => $first->json('meta.next_cursor')]));

        $first->assertJsonCount(2, 'data');
        $second->assertJsonCount(1, 'data')->assertJsonPath('meta.next_cursor', null);
    }

    public function test_prunes_entries_older_than_a_year(): void
    {
        $this->actingAs(User::factory()->create());
        Course::factory()->count(2)->create();
        Activity::query()->first()->forceFill(['created_at' => now()->subDays(Activity::KEEP_DAYS + 1)])->save();

        $this->artisan('model:prune', ['--model' => [Activity::class]])->assertSuccessful();

        $this->assertSame(1, Activity::count());
    }
}
