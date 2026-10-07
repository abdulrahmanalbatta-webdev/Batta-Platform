<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Course;
use App\Models\Lead;
use App\Models\Review;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Alerts\ContactMessageReceived;
use App\Notifications\Alerts\LeadReceived;
use App\Notifications\Alerts\RegistrationReceived;
use App\Notifications\Alerts\ReviewSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamAlertsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function signIn(Student $student): array
    {
        return ['Authorization' => 'Bearer '.$student->createToken('web')->plainTextToken];
    }

    public function test_registration_alerts_the_student_roles_by_bell_and_email(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $support = User::factory()->role(Role::Support)->create(['notification_preferences' => ['registrations' => false]]);
        $editor = User::factory()->role(Role::Editor)->create();
        $invited = User::factory()->role(Role::Support)->pending()->create();
        $student = Student::factory()->create();
        Course::factory()->published()->create(['slug' => 'nextjs']);

        $this->postJson(route('site.me.courses.store', 'nextjs'), [], $this->signIn($student))->assertCreated();

        Notification::assertSentTo($owner, RegistrationReceived::class, fn (RegistrationReceived $alert, array $channels): bool => $channels === ['database', 'mail']);
        Notification::assertSentTo($support, RegistrationReceived::class, fn (RegistrationReceived $alert, array $channels): bool => $channels === ['database']);
        Notification::assertNotSentTo([$editor, $invited], RegistrationReceived::class);
    }

    public function test_bell_entry_is_stored_without_waiting_for_the_queue(): void
    {
        $owner = User::factory()->owner()->create();
        $student = Student::factory()->create(['name' => 'نور', 'email' => 'nour@example.com', 'phone' => null]);
        Course::factory()->published()->create(['title' => 'Next.js', 'slug' => 'nextjs']);

        $this->postJson(route('site.me.courses.store', 'nextjs'), [], $this->signIn($student))->assertCreated();

        $notification = $owner->notifications()->sole();
        $this->assertSame('registrations', $notification->data['type']);
        $this->assertSame('تسجيل جديد في الدورة: Next.js', $notification->data['title']);
        $this->assertSame('نور · nour@example.com', $notification->data['meta']);
        $this->assertSame('students', $notification->data['page']);
        $this->assertSame(['q' => 'nour@example.com'], $notification->data['params']);
    }

    public function test_member_who_adds_a_lead_is_not_alerted_about_it(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson(route('api.leads.store'), ['name' => 'رامي', 'service' => 'ecommerce'])->assertCreated();

        Notification::assertSentTo($owner, LeadReceived::class, fn (LeadReceived $alert): bool => $alert->toArray($owner)['params'] === ['lead' => Lead::sole()->id]);
        Notification::assertNotSentTo($admin, LeadReceived::class);
    }

    public function test_pending_review_alerts_moderators(): void
    {
        Notification::fake();
        $support = User::factory()->role(Role::Support)->create();
        $editor = User::factory()->role(Role::Editor)->create();

        Review::factory()->create();
        Review::factory()->published()->create();

        Notification::assertSentToTimes($support, ReviewSubmitted::class, 1);
        Notification::assertSentToTimes($editor, ReviewSubmitted::class, 1);
    }

    public function test_message_from_contact_reopens_the_conversation_and_alerts_who_answers(): void
    {
        Notification::fake();
        $support = User::factory()->role(Role::Support)->create(['notification_preferences' => null]);
        $conversation = Conversation::factory()->create(['last_message_at' => now()->subDay()]);

        ConversationMessage::factory()->for($conversation)->create(['body' => 'مرحبا']);

        $this->assertTrue($conversation->fresh()->isUnread());
        $this->assertTrue($conversation->fresh()->last_message_at->isToday());
        // messages are bell-only unless the member switches the email on
        Notification::assertSentTo($support, ContactMessageReceived::class, fn (ContactMessageReceived $alert, array $channels): bool => $channels === ['database']
            && $alert->toArray($support)['params'] === ['c' => $conversation->id]);
    }

    public function test_team_reply_sends_no_alert(): void
    {
        Notification::fake();
        User::factory()->owner()->create();

        ConversationMessage::factory()->create(['from_contact' => false]);

        Notification::assertNothingSent();
    }
}
