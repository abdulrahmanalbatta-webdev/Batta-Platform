<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\TeamInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class TeamMemberControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_team_owner_first_with_viewer_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        $editor = User::factory()->create();
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($admin)->getJson(route('api.team.index'));

        $response->assertOk()
            ->assertJsonPath('data.0.id', $owner->id)
            ->assertJsonPath('data.0.can.update', false)
            ->assertJsonPath('data.1.id', $admin->id)
            ->assertJsonPath('data.1.is_you', true)
            ->assertJsonPath('data.2.id', $editor->id)
            ->assertJsonPath('data.2.can.delete', true)
            ->assertJsonPath('meta.can_invite', true)
            ->assertJsonPath('meta.roles.*.value', ['editor', 'support']);
    }

    public function test_editor_sees_team_but_cannot_invite(): void
    {
        $response = $this->actingAs(User::factory()->create())->getJson(route('api.team.index'));

        $response->assertOk()->assertJsonPath('meta.can_invite', false)->assertJsonPath('meta.roles', []);
    }

    public function test_admin_invites_pending_member_and_emails_invitation(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson(route('api.team.store'), [
            'name' => 'يوسف عودة',
            'email' => 'Yousef@Batta.dev',
            'role' => Role::Support->value,
        ]);

        $response->assertCreated()->assertJsonPath('data.pending', true)->assertJsonPath('data.role', 'support');
        $member = User::where('email', 'yousef@batta.dev')->sole();
        $this->assertSame(Role::Support, $member->role);
        Notification::assertSentTo($member, TeamInvitation::class, function (TeamInvitation $invitation) use ($member) {
            $url = $invitation->toMail($member)->actionUrl;

            return Password::broker('invitations')->tokenExists($member, $invitation->token)
                && str_contains($url, 'invite=1')
                && str_starts_with($url, route('password.reset', $invitation->token));
        });
    }

    public function test_admin_cannot_invite_another_admin(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->postJson(route('api.team.store'), [
            'name' => 'عضو',
            'email' => 'new@batta.dev',
            'role' => Role::Admin->value,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['role' => 'قيمة الصلاحية المختارة غير صالحة.']);
        $this->assertDatabaseMissing('users', ['email' => 'new@batta.dev']);
    }

    public function test_owner_can_invite_admin(): void
    {
        Notification::fake();

        $response = $this->actingAs(User::factory()->owner()->create())->postJson(route('api.team.store'), [
            'name' => 'مدير',
            'email' => 'admin2@batta.dev',
            'role' => Role::Admin->value,
        ]);

        $response->assertCreated()->assertJsonPath('data.role', 'admin');
    }

    public function test_editor_cannot_invite_and_gets_403(): void
    {
        $response = $this->actingAs(User::factory()->create())->postJson(route('api.team.store'), [
            'name' => 'عضو',
            'email' => 'new@batta.dev',
            'role' => Role::Support->value,
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'new@batta.dev']);
    }

    public function test_inviting_existing_email_returns_422(): void
    {
        User::factory()->create(['email' => 'sara@batta.dev']);

        $response = $this->actingAs(User::factory()->owner()->create())->postJson(route('api.team.store'), [
            'name' => 'سارة',
            'email' => 'sara@batta.dev',
            'role' => Role::Editor->value,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => 'البريد الإلكتروني مستخدم مسبقاً.']);
    }

    public function test_admin_changes_editor_role(): void
    {
        $editor = User::factory()->create();

        $response = $this->actingAs(User::factory()->admin()->create())->patchJson(route('api.team.update', $editor), ['role' => Role::Support->value]);

        $response->assertOk()->assertJsonPath('data.role_label', 'دعم فني');
        $this->assertSame(Role::Support, $editor->fresh()->role);
    }

    public function test_admin_cannot_change_owner_role_and_gets_403(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs(User::factory()->admin()->create())->patchJson(route('api.team.update', $owner), ['role' => Role::Editor->value]);

        $response->assertForbidden();
        $this->assertSame(Role::Owner, $owner->fresh()->role);
    }

    public function test_cannot_promote_member_to_owner(): void
    {
        $editor = User::factory()->create();

        $response = $this->actingAs(User::factory()->owner()->create())->patchJson(route('api.team.update', $editor), ['role' => Role::Owner->value]);

        $response->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertSame(Role::Editor, $editor->fresh()->role);
    }

    public function test_unknown_member_returns_404(): void
    {
        $response = $this->actingAs(User::factory()->owner()->create())->patchJson(route('api.team.update', 999), ['role' => Role::Editor->value]);

        $response->assertNotFound();
    }

    public function test_removing_member_deletes_them_and_their_sessions(): void
    {
        config(['session.driver' => 'database']);
        $editor = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'editor-device', 'user_id' => $editor->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        $response = $this->actingAs(User::factory()->owner()->create())->deleteJson(route('api.team.destroy', $editor));

        $response->assertNoContent();
        $this->assertModelMissing($editor);
        $this->assertDatabaseMissing('sessions', ['id' => 'editor-device']);
    }

    public function test_cannot_remove_yourself_and_gets_403(): void
    {
        $owner = User::factory()->owner()->create();

        $response = $this->actingAs($owner)->deleteJson(route('api.team.destroy', $owner));

        $response->assertForbidden();
        $this->assertModelExists($owner);
    }
}
