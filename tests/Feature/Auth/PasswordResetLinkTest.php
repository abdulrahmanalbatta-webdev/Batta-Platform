<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_sends_reset_link_to_member(): void
    {
        Notification::fake();
        $member = User::factory()->create(['email' => 'sara@batta.dev']);

        $response = $this->postJson(route('password.email'), ['email' => 'sara@batta.dev']);

        $response->assertOk()->assertJsonPath('message', __('passwords.sent'));
        Notification::assertSentTo($member, ResetPassword::class, function (ResetPassword $notification) use ($member) {
            return str_starts_with($notification->toMail($member)->actionUrl, route('password.reset', $notification->token));
        });
    }

    public function test_unknown_email_gets_the_same_reply_and_no_email(): void
    {
        Notification::fake();

        $response = $this->postJson(route('password.email'), ['email' => 'nobody@batta.dev']);

        $response->assertOk()->assertJsonPath('message', __('passwords.sent'));
        Notification::assertNothingSent();
    }

    public function test_invalid_email_returns_422(): void
    {
        $response = $this->postJson(route('password.email'), ['email' => 'not-an-email']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'حقل البريد الإلكتروني يجب أن يكون بريداً إلكترونياً صحيحاً.']);
    }
}
