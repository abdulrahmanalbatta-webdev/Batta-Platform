<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\Lead;
use App\Models\User;
use App\Notifications\Alerts\LeadReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    private function memberWithAlerts(int $count): User
    {
        $member = User::factory()->owner()->create();
        Lead::withoutEvents(fn () => Lead::factory()->count($count)->create())
            ->each(fn (Lead $lead) => $member->notifyNow(new LeadReceived($lead), ['database']));

        return $member;
    }

    public function test_lists_latest_notifications_with_unread_count(): void
    {
        $member = $this->memberWithAlerts(2);
        $member->notifications()->first()->markAsRead();

        $response = $this->actingAs($member)->getJson(route('api.notifications.index'));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('unread_count', 1)
            ->assertJsonPath('data.0.type', 'leads')
            ->assertJsonPath('data.0.page', 'leads')
            ->assertJsonStructure(['data' => [['id', 'title', 'meta', 'params', 'unread', 'at']]]);
    }

    public function test_marks_one_and_then_all_as_read(): void
    {
        $member = $this->memberWithAlerts(3);
        $first = $member->notifications()->first();

        $this->actingAs($member)->postJson(route('api.notifications.read', $first->id))->assertNoContent();
        $this->assertSame(2, $member->unreadNotifications()->count());

        $this->actingAs($member)->postJson(route('api.notifications.read-all'))->assertNoContent();
        $this->assertSame(0, $member->unreadNotifications()->count());
    }

    public function test_cannot_read_another_members_notification(): void
    {
        $other = $this->memberWithAlerts(1);

        $response = $this->actingAs(User::factory()->create())->postJson(route('api.notifications.read', $other->notifications()->first()->id));

        $response->assertNotFound();
        $this->assertSame(1, $other->unreadNotifications()->count());
    }

    public function test_saves_email_preferences_for_the_members_alert_types(): void
    {
        $support = User::factory()->role(Role::Support)->create();

        $response = $this->actingAs($support)->putJson(route('api.notification-preferences.update'), ['messages' => true]);

        $response->assertOk()->assertExactJson(['data' => ['registrations' => true, 'reviews' => true, 'comments' => true, 'messages' => true, 'students' => true]]);
        $this->actingAs($support)->putJson(route('api.notification-preferences.update'), ['reviews' => false])
            ->assertExactJson(['data' => ['registrations' => true, 'reviews' => false, 'comments' => true, 'messages' => true, 'students' => true]]);
    }

    public function test_preferences_must_be_booleans_of_known_types(): void
    {
        $response = $this->actingAs(User::factory()->create())->putJson(route('api.notification-preferences.update'), ['reviews' => 'maybe', 'spam' => true]);

        $response->assertUnprocessable()->assertJsonValidationErrors('reviews');
    }

    public function test_own_preferences_come_with_the_signed_in_member_only(): void
    {
        $editor = User::factory()->role(Role::Editor)->create();

        $this->actingAs($editor)->get(route('settings.index'))->assertSee('"email_preferences":{"reviews":true,"comments":true}', escape: false);
        $this->actingAs(User::factory()->owner()->create())->getJson(route('api.team.index'))
            ->assertJsonPath('data.0.email_preferences.registrations', true)
            ->assertJsonPath('data.1.email', $editor->email)
            ->assertJsonMissingPath('data.1.email_preferences');
    }
}
