<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class NewPasswordControllerTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'NewPassword1';

    public function test_valid_token_sets_new_password(): void
    {
        $member = User::factory()->create();
        $token = Password::createToken($member);

        $response = $this->postJson(route('api.auth.password.store'), [
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

        $response = $this->postJson(route('api.auth.password.store'), [
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

        $response = $this->postJson(route('api.auth.password.store'), [
            'token' => Password::createToken($member),
            'email' => $member->email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password' => 'حقل كلمة المرور يجب أن يحتوي على حرف كبير وحرف صغير على الأقل.']);
    }

    public function test_invitation_link_sets_password_and_confirms_pending_member(): void
    {
        $member = User::factory()->pending()->create();
        $token = Password::broker('invitations')->createToken($member);

        $response = $this->postJson(route('api.auth.password.store'), [
            'token' => $token,
            'email' => $member->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
            'invite' => true,
        ]);

        $response->assertOk();
        $this->assertFalse($member->fresh()->isPending());
    }

    public function test_invitation_link_still_works_after_reset_links_expire(): void
    {
        $member = User::factory()->pending()->create();
        $token = Password::broker('invitations')->createToken($member);
        $this->travel(3)->days();
        $payload = [
            'token' => $token,
            'email' => $member->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];

        $this->postJson(route('api.auth.password.store'), $payload)->assertUnprocessable();
        $this->postJson(route('api.auth.password.store'), [...$payload, 'invite' => true])->assertOk();
    }

    public function test_invitation_link_expires_after_seven_days(): void
    {
        $member = User::factory()->pending()->create();
        $token = Password::broker('invitations')->createToken($member);
        $this->travel(8)->days();

        $response = $this->postJson(route('api.auth.password.store'), [
            'token' => $token,
            'email' => $member->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
            'invite' => true,
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => __('passwords.token')]);
        $this->assertTrue($member->fresh()->isPending());
    }
}
