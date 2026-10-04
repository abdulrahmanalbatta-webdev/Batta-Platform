<?php

namespace Tests\Feature;

use App\Actions\Orders\CompleteOrder;
use App\Enums\Role;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Review;
use App\Models\User;
use App\Notifications\Alerts\ContactMessageReceived;
use App\Notifications\Alerts\LeadReceived;
use App\Notifications\Alerts\OrderPaid;
use App\Notifications\Alerts\ReviewSubmitted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TeamAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_order_alerts_the_sales_roles_by_bell_and_email(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $accountant = User::factory()->role(Role::Accountant)->create(['notification_preferences' => ['orders' => false]]);
        $editor = User::factory()->role(Role::Editor)->create();
        $invited = User::factory()->role(Role::Accountant)->pending()->create();

        app(CompleteOrder::class)->handle(Order::factory()->pending()->create());

        Notification::assertSentTo($owner, OrderPaid::class, fn (OrderPaid $alert, array $channels): bool => $channels === ['database', 'mail']);
        Notification::assertSentTo($accountant, OrderPaid::class, fn (OrderPaid $alert, array $channels): bool => $channels === ['database']);
        Notification::assertNotSentTo([$editor, $invited], OrderPaid::class);
    }

    public function test_bell_entry_is_stored_without_waiting_for_the_queue(): void
    {
        $owner = User::factory()->owner()->create();
        $order = Order::factory()->pending()->create(['item_name' => 'Next.js', 'total' => 79]);

        app(CompleteOrder::class)->handle($order);

        $notification = $owner->notifications()->sole();
        $this->assertSame('طلب مكتمل: Next.js', $notification->data['title']);
        $this->assertSame(['q' => $order->number()], $notification->data['params']);
        $this->assertStringEndsWith("· \u{2066}79\$\u{2069}", $notification->data['meta']);
    }

    public function test_member_who_adds_a_lead_is_not_alerted_about_it(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->postJson(route('api.leads.store'), ['name' => 'رامي', 'service' => 'stores'])->assertCreated();

        Notification::assertSentTo($owner, LeadReceived::class, fn (LeadReceived $alert): bool => $alert->toArray($owner)['params'] === ['lead' => Lead::sole()->id]);
        Notification::assertNotSentTo($admin, LeadReceived::class);
    }

    public function test_pending_review_alerts_moderators(): void
    {
        Notification::fake();
        $support = User::factory()->role(Role::Support)->create();
        $accountant = User::factory()->role(Role::Accountant)->create();

        Review::factory()->create();
        Review::factory()->published()->create();

        Notification::assertSentToTimes($support, ReviewSubmitted::class, 1);
        Notification::assertNotSentTo($accountant, ReviewSubmitted::class);
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
