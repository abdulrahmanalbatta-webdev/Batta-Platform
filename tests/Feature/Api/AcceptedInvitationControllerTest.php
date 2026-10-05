<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AcceptedInvitationControllerTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_PASSWORD = 'NewPassword1';

    /**
     * @return array<string, string>
     */
    private function payload(User $member, string $token): array
    {
        return [
            'token' => $token,
            'email' => $member->email,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ];
    }

    public function test_sets_first_password_and_confirms_pending_member(): void
    {
        $member = User::factory()->pending()->create();
        $token = Password::broker('invitations')->createToken($member);

        $response = $this->postJson(route('api.invitations.accept'), $this->payload($member, $token));

        $response->assertOk()->assertJsonPath('message', __('passwords.reset'));
        $member->refresh();
        $this->assertFalse($member->isPending());
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $member->password));
    }

    public function test_invitation_is_still_valid_after_three_days(): void
    {
        $member = User::factory()->pending()->create();
        $token = Password::broker('invitations')->createToken($member);
        $this->travel(3)->days();

        $response = $this->postJson(route('api.invitations.accept'), $this->payload($member, $token));

        $response->assertOk();
    }

    public function test_invitation_expires_after_seven_days(): void
    {
        $member = User::factory()->pending()->create();
        $token = Password::broker('invitations')->createToken($member);
        $this->travel(8)->days();

        $response = $this->postJson(route('api.invitations.accept'), $this->payload($member, $token));

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => __('passwords.token')]);
        $this->assertTrue($member->fresh()->isPending());
    }

    public function test_expired_reset_link_cannot_be_used_as_an_invitation(): void
    {
        $member = User::factory()->create();
        $token = Password::createToken($member);
        $this->travel(2)->hours();

        $response = $this->postJson(route('api.invitations.accept'), $this->payload($member, $token));

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => __('passwords.token')]);
        $this->assertFalse(Hash::check(self::NEW_PASSWORD, $member->fresh()->password));
    }

    public function test_weak_password_returns_422(): void
    {
        $member = User::factory()->pending()->create();
        $token = Password::broker('invitations')->createToken($member);

        $response = $this->postJson(route('api.invitations.accept'), [...$this->payload($member, $token), 'password' => 'short', 'password_confirmation' => 'short']);

        $response->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertTrue($member->fresh()->isPending());
    }
}
