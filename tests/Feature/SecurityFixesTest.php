<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Notifications\TeamInvitation;
use App\Support\PlatformSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_link_uses_app_url_not_a_forged_host(): void
    {
        Notification::fake();
        config(['app.url' => 'https://dash.batta.dev']);
        $owner = User::factory()->owner()->create();

        $this->withHeader('Host', 'evil.test')->postJson('http://evil.test/dashboard/api/v1/auth/forgot-password', ['email' => $owner->email])->assertOk();

        Notification::assertSentTo($owner, ResetPassword::class, function (ResetPassword $notification) use ($owner): bool {
            $url = $notification->toMail($owner)->actionUrl;

            return str_starts_with($url, 'https://dash.batta.dev/reset-password/') && ! str_contains($url, 'evil.test');
        });
    }

    public function test_invitation_link_uses_app_url_not_a_forged_host(): void
    {
        Notification::fake();
        config(['app.url' => 'https://dash.batta.dev']);

        $this->actingAs(User::factory()->owner()->create())
            ->withHeader('Host', 'evil.test')
            ->postJson('http://evil.test/dashboard/api/v1/team', ['name' => 'سارة', 'email' => 'sara@batta.dev', 'role' => 'editor'])
            ->assertCreated();

        Notification::assertSentTo(User::where('email', 'sara@batta.dev')->sole(), TeamInvitation::class, fn (TeamInvitation $invitation, array $channels, User $member): bool => str_starts_with($invitation->toMail($member)->actionUrl, 'https://dash.batta.dev/'));
    }

    public function test_password_reset_signs_out_every_other_session(): void
    {
        config(['session.driver' => 'database']);
        $member = User::factory()->create();
        DB::table('sessions')->insert(['id' => 'stolen', 'user_id' => $member->id, 'ip_address' => '1.2.3.4', 'user_agent' => 'x', 'payload' => '', 'last_activity' => now()->getTimestamp()]);
        $token = Password::broker()->createToken($member);

        $this->postJson(route('password.update'), ['token' => $token, 'email' => $member->email, 'password' => 'NewPassw0rd', 'password_confirmation' => 'NewPassw0rd'])->assertOk();

        $this->assertDatabaseMissing('sessions', ['id' => 'stolen']);
    }

    public function test_reset_and_invitation_do_not_reveal_who_is_on_the_team(): void
    {
        User::factory()->create(['email' => 'member@batta.dev']);
        $payload = fn (string $email): array => ['token' => 'bad-token', 'email' => $email, 'password' => 'NewPassw0rd', 'password_confirmation' => 'NewPassw0rd'];

        $unknown = $this->postJson(route('password.update'), $payload('nobody@batta.dev'))->json('errors.email.0');
        $known = $this->postJson(route('password.update'), $payload('member@batta.dev'))->json('errors.email.0');
        $this->assertSame($known, $unknown);

        $unknown = $this->postJson(route('api.invitations.accept'), $payload('nobody@batta.dev'))->json('errors.email.0');
        $known = $this->postJson(route('api.invitations.accept'), $payload('member@batta.dev'))->json('errors.email.0');
        $this->assertSame($known, $unknown);
    }

    public function test_admin_cannot_change_where_mail_goes(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['mail_host' => 'smtp.attacker.test', 'mail_password' => 'attacker', 'mail_from_address' => 'attacker@attacker.test'] as $key => $value) {
            $this->actingAs($admin)->putJson(route('api.settings.update'), [$key => $value])->assertForbidden();
        }
        $this->actingAs($admin)->postJson(route('api.settings.test-email'))->assertForbidden();

        // everything else stays theirs
        $this->actingAs($admin)->putJson(route('api.settings.update'), ['currency' => 'SAR', 'weekly_report' => false])->assertOk();
    }

    public function test_changing_the_smtp_server_forgets_the_stored_password(): void
    {
        $settings = app(PlatformSettings::class);
        $settings->update(['mail_host' => 'smtp.good.test', 'mail_username' => 'me', 'mail_password' => 'secret']);

        $settings->update(['mail_port' => 465]);
        $this->assertSame('secret', $settings->get('mail_password'));

        $settings->update(['mail_host' => 'smtp.other.test']);
        $this->assertNull($settings->get('mail_password'));
        $this->assertNull(Setting::find('mail_password'));
    }

    public function test_secret_hints_are_for_the_owner_only(): void
    {
        app(PlatformSettings::class)->update(['mail_password' => 'smtp_abcd1234']);

        $this->actingAs(User::factory()->admin()->create())->getJson(route('api.settings.show'))->assertJsonPath('data.mail_password', ['set' => true, 'hint' => null]);
        $this->actingAs(User::factory()->owner()->create())->getJson(route('api.settings.show'))->assertJsonPath('data.mail_password.hint', '••••1234');
    }
}
