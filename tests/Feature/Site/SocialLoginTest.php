<?php

namespace Tests\Feature\Site;

use App\Models\Student;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private const GOOGLE_CLIENT = '1234-abc.apps.googleusercontent.com';

    protected function setUp(): void
    {
        parent::setUp();

        app(PlatformSettings::class)->update(['google_client_id' => self::GOOGLE_CLIENT, 'facebook_app_id' => '555666777', 'facebook_app_secret' => 'fb-secret']);
        Notification::fake();
    }

    public function test_a_new_google_account_gives_its_phone_once_then_is_signed_in(): void
    {
        $this->fakeGoogle();

        $this->postJson(route('site.auth.social', 'google'), ['token' => 'g-token'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
        $this->assertDatabaseCount('students', 0);

        $this->postJson(route('site.auth.social', 'google'), ['token' => 'g-token', 'phone' => '+970 59 000 0000'])
            ->assertCreated()
            ->assertJsonStructure(['token'])
            ->assertJsonPath('data.email', 'sara@example.com')
            ->assertJsonPath('data.name', 'Sara Ali');

        $student = Student::query()->sole();
        $this->assertSame('google-sub-1', $student->google_id);
        $this->assertSame('+970 59 000 0000', $student->phone);
        $this->assertNull($student->password);
    }

    public function test_an_existing_student_is_linked_by_email_and_found_by_id_after(): void
    {
        $student = Student::factory()->withPassword()->create(['email' => 'sara@example.com']);
        $this->fakeGoogle();

        $this->postJson(route('site.auth.social', 'google'), ['token' => 'g-token'])->assertOk()->assertJsonPath('data.email', 'sara@example.com');
        $this->assertSame('google-sub-1', $student->fresh()->google_id);

        // a changed Google address still reaches the same account
        $student->update(['email' => 'new@example.com']);
        $this->postJson(route('site.auth.social', 'google'), ['token' => 'g-token'])->assertOk()->assertJsonPath('data.email', 'new@example.com');
        $this->assertDatabaseCount('students', 1);
    }

    public function test_a_google_token_issued_to_another_app_is_refused(): void
    {
        $this->fakeGoogle(audience: 'someone-else.apps.googleusercontent.com');

        $this->postJson(route('site.auth.social', 'google'), ['token' => 'g-token', 'phone' => '+970 59 000 0000'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('token');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_facebook_signs_in_with_a_token_of_this_app(): void
    {
        $this->fakeFacebook('555666777');

        $this->postJson(route('site.auth.social', 'facebook'), ['token' => 'f-token', 'phone' => '+970 59 111 1111'])->assertCreated()->assertJsonPath('data.email', 'omar@example.com');
        $this->assertSame('fb-1', Student::query()->sole()->facebook_id);
    }

    public function test_a_facebook_token_of_another_app_is_refused(): void
    {
        $this->fakeFacebook('999');

        $this->postJson(route('site.auth.social', 'facebook'), ['token' => 'f-token', 'phone' => '+970 59 111 1111'])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->assertDatabaseCount('students', 0);
    }

    public function test_suspended_students_closed_sign_ups_and_unset_providers_are_refused(): void
    {
        $this->fakeGoogle();
        Student::factory()->suspended()->create(['email' => 'sara@example.com']);
        $this->postJson(route('site.auth.social', 'google'), ['token' => 'g-token'])->assertForbidden();

        Student::query()->delete();
        app(PlatformSettings::class)->update(['registration_open' => false]);
        $this->postJson(route('site.auth.social', 'google'), ['token' => 'g-token', 'phone' => '+970 59 000 0000'])->assertForbidden();

        app(PlatformSettings::class)->update(['facebook_app_id' => null]);
        $this->postJson(route('site.auth.social', 'facebook'), ['token' => 'f-token'])->assertNotFound();
        $this->postJson('/api/v1/auth/social/twitter', ['token' => 'x'])->assertNotFound();
    }

    public function test_the_site_gets_the_ids_but_never_the_facebook_secret(): void
    {
        $this->getJson(route('site.settings'))
            ->assertJsonPath('data.google_client_id', self::GOOGLE_CLIENT)
            ->assertJsonPath('data.facebook_app_id', '555666777')
            ->assertJsonMissingPath('data.facebook_app_secret')
            ->assertDontSee('fb-secret');
    }

    private function fakeGoogle(string $audience = self::GOOGLE_CLIENT): void
    {
        Http::fake([
            'oauth2.googleapis.com/tokeninfo*' => Http::response(['aud' => $audience, 'sub' => 'google-sub-1', 'email' => 'Sara@Example.com', 'email_verified' => 'true']),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response(['sub' => 'google-sub-1', 'name' => 'Sara Ali']),
        ]);
    }

    private function fakeFacebook(string $tokenAppId): void
    {
        Http::fake([
            'graph.facebook.com/debug_token*' => Http::response(['data' => ['is_valid' => true, 'app_id' => $tokenAppId, 'user_id' => 'fb-1']]),
            'graph.facebook.com/me*' => Http::response(['id' => 'fb-1', 'name' => 'Omar', 'email' => 'Omar@Example.com']),
        ]);
    }
}
