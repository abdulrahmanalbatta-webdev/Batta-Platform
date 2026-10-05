<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Failed logins Fortify allows per email and IP each minute.
     */
    private const MAX_ATTEMPTS = 5;

    public function test_valid_credentials_sign_in_and_return_dashboard_redirect(): void
    {
        $member = User::factory()->create(['email' => 'sara@batta.dev']);

        $response = $this->postJson(route('login.store'), ['email' => 'sara@batta.dev', 'password' => 'password']);

        $response->assertOk()
            ->assertJsonPath('data.email', 'sara@batta.dev')
            ->assertJsonPath('redirect', route('dashboard'));
        $this->assertAuthenticatedAs($member);
        $this->assertNotNull($member->fresh()->last_login_at);
    }

    public function test_sign_in_returns_page_guest_was_sent_away_from(): void
    {
        User::factory()->create(['email' => 'sara@batta.dev']);
        $this->get(route('courses.index'))->assertRedirect(route('login'));

        $response = $this->postJson(route('login.store'), ['email' => 'sara@batta.dev', 'password' => 'password']);

        $response->assertJsonPath('redirect', route('courses.index'));
    }

    public function test_wrong_password_returns_422_with_arabic_message(): void
    {
        User::factory()->create(['email' => 'sara@batta.dev']);

        $response = $this->postJson(route('login.store'), ['email' => 'sara@batta.dev', 'password' => 'wrong-password']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
        $this->assertGuest();
    }

    public function test_empty_payload_returns_422_for_email_and_password(): void
    {
        $response = $this->postJson(route('login.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'email' => 'حقل البريد الإلكتروني مطلوب.',
                'password' => 'حقل كلمة المرور مطلوب.',
            ]);
    }

    public function test_too_many_failed_attempts_return_429_even_with_correct_password(): void
    {
        User::factory()->create(['email' => 'sara@batta.dev']);

        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $this->postJson(route('login.store'), ['email' => 'sara@batta.dev', 'password' => 'wrong-password']);
        }
        $response = $this->postJson(route('login.store'), ['email' => 'sara@batta.dev', 'password' => 'password']);

        $response->assertTooManyRequests()->assertJsonValidationErrors('email');
        $this->assertStringContainsString('محاولات دخول كثيرة', $response->json('errors.email.0'));
        $this->assertGuest();
    }

    public function test_remember_me_sets_remember_cookie(): void
    {
        User::factory()->create(['email' => 'sara@batta.dev']);

        $response = $this->postJson(route('login.store'), ['email' => 'sara@batta.dev', 'password' => 'password', 'remember' => true]);

        $response->assertOk();
        $this->assertNotEmpty(collect($response->headers->getCookies())->filter(fn ($cookie) => str_starts_with($cookie->getName(), 'remember_web_')));
    }

    public function test_logout_signs_out_and_returns_204(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->postJson(route('logout'));

        $response->assertNoContent();
        $this->assertGuest('web');
    }

    public function test_api_returns_401_json_for_guest(): void
    {
        $response = $this->get(route('api.team.index'));

        $response->assertUnauthorized()->assertJsonStructure(['message']);
    }
}
