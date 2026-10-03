<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'NewPassword1';

    public function test_valid_token_sets_new_password(): void
    {
        $member = User::factory()->create();
        $token = Password::createToken($member);

        $response = $this->postJson(route('password.update'), [
            'token' => $token,
            'email' => $member->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $response->assertOk()->assertJsonPath('message', __('passwords.reset'));
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $member->fresh()->password));
    }

    public function test_invalid_token_returns_422(): void
    {
        $member = User::factory()->create();

        $response = $this->postJson(route('password.update'), [
            'token' => 'invalid-token',
            'email' => $member->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => __('passwords.token')]);
        $this->assertFalse(Hash::check(self::NEW_PASSWORD, $member->fresh()->password));
    }

    public function test_weak_password_returns_422(): void
    {
        $member = User::factory()->create();

        $response = $this->postJson(route('password.update'), [
            'token' => Password::createToken($member),
            'email' => $member->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'حقل كلمة المرور يجب أن يحتوي على حرف كبير وحرف صغير على الأقل.']);
    }

    public function test_reset_confirms_a_pending_member(): void
    {
        $member = User::factory()->pending()->create();

        $this->postJson(route('password.update'), [
            'token' => Password::createToken($member),
            'email' => $member->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertOk();

        $this->assertFalse($member->fresh()->isPending());
    }

    public function test_reset_link_expires_after_an_hour(): void
    {
        $member = User::factory()->create();
        $token = Password::createToken($member);
        $this->travel(61)->minutes();

        $response = $this->postJson(route('password.update'), [
            'token' => $token,
            'email' => $member->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => __('passwords.token')]);
    }

    public function test_invitation_token_cannot_be_used_as_reset_link(): void
    {
        $member = User::factory()->pending()->create();
        $token = Password::broker('invitations')->createToken($member);

        $response = $this->postJson(route('password.update'), [
            'token' => $token,
            'email' => $member->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => __('passwords.token')]);
    }
}
