<?php

namespace Tests\Feature\Site;

use App\Enums\LeadService;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Alerts\ContactMessageReceived;
use App\Notifications\Alerts\LeadReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SiteFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_message_lands_in_the_inbox_and_alerts_the_team(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();

        $this->postJson(route('site.contact'), ['name' => 'زائر', 'email' => 'visitor@example.com', 'message' => 'عندي سؤال عن الدورات'])
            ->assertCreated();

        $conversation = Conversation::query()->sole();
        $this->assertNull($conversation->student_id);
        $this->assertSame('visitor@example.com', $conversation->email);
        $this->assertNull($conversation->read_at);
        $this->assertSame('عندي سؤال عن الدورات', $conversation->messages()->sole()->body);
        Notification::assertSentTo($owner, ContactMessageReceived::class);
    }

    public function test_a_signed_in_student_writes_into_their_own_conversation(): void
    {
        $student = Student::factory()->create(['email' => 'sara@example.com']);
        $headers = ['Authorization' => 'Bearer '.$student->createToken('web')->plainTextToken];

        foreach (['الرسالة الأولى هنا', 'الرسالة الثانية هنا'] as $message) {
            $this->postJson(route('site.contact'), ['name' => 'أي اسم', 'email' => 'other@example.com', 'message' => $message], $headers)->assertCreated();
        }

        $conversation = Conversation::query()->sole();
        $this->assertSame($student->id, $conversation->student_id);
        $this->assertSame('sara@example.com', $conversation->email);
        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_a_project_request_becomes_a_new_lead(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();

        $this->postJson(route('site.project-requests'), [
            'name' => 'أحمد',
            'company' => 'شركة النور',
            'email' => 'ahmad@example.com',
            'service' => LeadService::Stores->value,
            'budget' => 3000,
            'details' => 'نحتاج متجراً إلكترونياً بالعربية',
        ])->assertCreated();

        $lead = Lead::query()->sole();
        $this->assertSame('new', $lead->stage->value);
        $this->assertSame(LeadService::Stores, $lead->service);
        $this->assertSame('نحتاج متجراً إلكترونياً بالعربية', $lead->note);
        Notification::assertSentTo($owner, LeadReceived::class);

        $this->postJson(route('site.project-requests'), ['name' => 'أ', 'email' => 'x', 'service' => 'nope', 'details' => ''])
            ->assertJsonValidationErrors(['email', 'service', 'details']);
    }

    public function test_the_bot_trap_keeps_nothing_but_answers_the_same(): void
    {
        $this->postJson(route('site.contact'), ['name' => 'bot', 'email' => 'bot@example.com', 'message' => 'buy cheap things now', 'website' => 'http://spam.test'])
            ->assertCreated();
        $this->postJson(route('site.project-requests'), ['name' => 'bot', 'email' => 'bot@example.com', 'service' => 'websites', 'details' => 'buy cheap things now', 'website' => 'http://spam.test'])
            ->assertCreated();

        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(0, Lead::query()->count());
    }

    public function test_forms_are_rate_limited_per_address(): void
    {
        $message = ['name' => 'زائر', 'email' => 'visitor@example.com', 'message' => 'رسالة تجريبية طويلة'];

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('site.contact'), $message)->assertCreated();
        }

        $this->postJson(route('site.contact'), $message)->assertTooManyRequests();
        $this->postJson(route('site.project-requests'), [])->assertUnprocessable();
    }
}
