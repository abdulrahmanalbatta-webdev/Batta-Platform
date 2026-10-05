<?php

namespace Tests\Feature\Site;

use App\Models\Activity;
use App\Models\Student;
use App\Models\User;
use App\Notifications\Alerts\StudentRegistered;
use App\Notifications\StudentPasswordReset;
use App\Support\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class StudentAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_registers_and_gets_a_token(): void
    {
        $response = $this->postJson(route('site.auth.register'), [
            'name' => 'ليلى',
            'email' => 'layla@example.com',
            'phone' => '+970 59 123 4567',
            'password' => 'Secret-pass-1',
            'password_confirmation' => 'Secret-pass-1',
        ]);

        $response->assertCreated()->assertJsonPath('data.email', 'layla@example.com')->assertJsonMissingPath('data.password');
        $student = Student::query()->where('email', 'layla@example.com')->sole();
        $this->assertTrue(Hash::check('Secret-pass-1', $student->password));

        $this->getJson(route('site.me.show'), ['Authorization' => 'Bearer '.$response->json('token')])
            ->assertOk()
            ->assertJsonPath('data.name', 'ليلى');
        $this->assertSame(0, Activity::query()->count());
    }

    public function test_registration_can_be_closed_and_known_addresses_are_refused(): void
    {
        Student::factory()->create(['email' => 'known@example.com']);
        $data = ['name' => 'س', 'email' => 'known@example.com', 'phone' => '+970591234567', 'password' => 'Secret-pass-1', 'password_confirmation' => 'Secret-pass-1'];

        $this->postJson(route('site.auth.register'), $data)->assertJsonValidationErrors('email');

        app(PlatformSettings::class)->update(['registration_open' => false]);
        $this->postJson(route('site.auth.register'), [...$data, 'email' => 'new@example.com'])->assertForbidden();
    }

    public function test_login_returns_a_token_and_logout_revokes_it(): void
    {
        Student::factory()->withPassword()->create(['email' => 'sara@example.com']);

        $token = $this->postJson(route('site.auth.login'), ['email' => 'Sara@Example.com', 'password' => 'Secret-pass-1', 'device_name' => 'iPhone'])
            ->assertOk()
            ->json('token');

        $this->assertStringContainsString('|batta_', $token);
        $this->postJson(route('site.auth.logout'), [], ['Authorization' => 'Bearer '.$token])->assertNoContent();

        $this->app['auth']->forgetGuards();
        $this->getJson(route('site.me.show'), ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }

    public function test_wrong_passwords_and_accounts_without_one_get_the_same_answer(): void
    {
        Student::factory()->withPassword()->create(['email' => 'sara@example.com']);
        Student::factory()->create(['email' => 'nopass@example.com']);

        $this->postJson(route('site.auth.login'), ['email' => 'sara@example.com', 'password' => 'wrong'])->assertJsonValidationErrors('email');
        $this->postJson(route('site.auth.login'), ['email' => 'nopass@example.com', 'password' => 'anything'])->assertJsonValidationErrors('email');
    }

    public function test_suspended_students_cannot_sign_in_and_lose_their_tokens(): void
    {
        $student = Student::factory()->withPassword()->create(['email' => 'sara@example.com']);
        $token = $student->createToken('web')->plainTextToken;

        $student->forceFill(['suspended_at' => now()])->save();

        $this->assertSame(0, $student->tokens()->count());
        $this->getJson(route('site.me.show'), ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
        $this->postJson(route('site.auth.login'), ['email' => 'sara@example.com', 'password' => 'Secret-pass-1'])->assertForbidden();
    }

    public function test_a_student_token_is_never_a_dashboard_session(): void
    {
        $student = Student::factory()->withPassword()->create();
        $token = $student->createToken('web')->plainTextToken;

        $this->getJson(route('api.dashboard'), ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    }

    public function test_forgot_password_mails_a_link_to_the_site_without_revealing_accounts(): void
    {
        Notification::fake();
        app(PlatformSettings::class)->update(['site_url' => 'https://batta.dev']);
        $student = Student::factory()->create(['email' => 'sara@example.com']);

        $this->postJson(route('site.auth.forgot-password'), ['email' => 'sara@example.com'])->assertOk();
        $this->postJson(route('site.auth.forgot-password'), ['email' => 'nobody@example.com'])->assertOk();

        Notification::assertSentTo($student, StudentPasswordReset::class, function (StudentPasswordReset $notification) use ($student): bool {
            return str_starts_with($notification->toMail($student)->actionUrl, 'https://batta.dev/reset-password?token=');
        });
        Notification::assertSentTimes(StudentPasswordReset::class, 1);
    }

    public function test_reset_password_sets_it_and_signs_out_everywhere(): void
    {
        $student = Student::factory()->create(['email' => 'sara@example.com']);
        $student->createToken('old');
        $token = Password::broker('students')->createToken($student);

        $this->postJson(route('site.auth.reset-password'), [
            'token' => $token,
            'email' => 'sara@example.com',
            'password' => 'New-secret-2',
            'password_confirmation' => 'New-secret-2',
        ])->assertOk();

        $this->assertTrue(Hash::check('New-secret-2', $student->fresh()->password));
        $this->assertSame(0, $student->tokens()->count());

        $this->postJson(route('site.auth.reset-password'), [
            'token' => $token,
            'email' => 'nobody@example.com',
            'password' => 'New-secret-2',
            'password_confirmation' => 'New-secret-2',
        ])->assertJsonValidationErrors('email');
    }

    public function test_changing_the_email_needs_the_current_password(): void
    {
        $student = Student::factory()->withPassword()->create(['email' => 'sara@example.com']);
        $headers = ['Authorization' => 'Bearer '.$student->createToken('web')->plainTextToken];

        $this->putJson(route('site.me.update'), ['name' => 'سارة', 'email' => 'sara@example.com', 'phone' => '+970591234567', 'country' => 'فلسطين'], $headers)
            ->assertOk()
            ->assertJsonPath('data.name', 'سارة');

        $this->putJson(route('site.me.update'), ['name' => 'سارة', 'email' => 'new@example.com', 'phone' => '+970591234567'], $headers)
            ->assertJsonValidationErrors('current_password');

        $this->putJson(route('site.me.update'), ['name' => 'سارة', 'email' => 'new@example.com', 'phone' => '+970591234567', 'current_password' => 'Secret-pass-1'], $headers)
            ->assertOk()
            ->assertJsonPath('data.email', 'new@example.com');
    }

    public function test_changing_the_password_signs_out_the_other_devices(): void
    {
        $student = Student::factory()->withPassword()->create();
        $student->createToken('laptop');
        $headers = ['Authorization' => 'Bearer '.$student->createToken('phone')->plainTextToken];

        $this->putJson(route('site.me.password'), ['current_password' => 'wrong', 'password' => 'New-secret-2', 'password_confirmation' => 'New-secret-2'], $headers)
            ->assertJsonValidationErrors('current_password');

        $this->putJson(route('site.me.password'), ['current_password' => 'Secret-pass-1', 'password' => 'New-secret-2', 'password_confirmation' => 'New-secret-2'], $headers)
            ->assertOk();

        $this->assertSame(['phone'], $student->tokens()->pluck('name')->all());
        $this->assertTrue(Hash::check('New-secret-2', $student->fresh()->password));
    }

    public function test_login_is_rate_limited(): void
    {
        Student::factory()->withPassword()->create(['email' => 'sara@example.com']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('site.auth.login'), ['email' => 'sara@example.com', 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson(route('site.auth.login'), ['email' => 'sara@example.com', 'password' => 'Secret-pass-1'])->assertTooManyRequests();
    }

    public function test_a_new_registration_alerts_the_team_with_the_whatsapp_number(): void
    {
        Notification::fake();
        $owner = User::factory()->owner()->create();

        $this->postJson(route('site.auth.register'), [
            'name' => 'محمد',
            'email' => 'mo@example.com',
            'phone' => 'not a phone',
            'password' => 'Secret-pass-1',
            'password_confirmation' => 'Secret-pass-1',
        ])->assertJsonValidationErrors('phone');

        $this->postJson(route('site.auth.register'), [
            'name' => 'محمد',
            'email' => 'mo@example.com',
            'phone' => '+970 59 123 4567',
            'password' => 'Secret-pass-1',
            'password_confirmation' => 'Secret-pass-1',
        ])->assertCreated()->assertJsonPath('data.phone', '+970 59 123 4567');

        Notification::assertSentTo($owner, StudentRegistered::class, fn (StudentRegistered $alert): bool => str_contains($alert->toArray($owner)['meta'], '+970 59 123 4567'));

        $this->actingAs($owner)->getJson(route('api.students.index'))
            ->assertJsonPath('data.0.phone', '+970 59 123 4567')
            ->assertJsonPath('data.0.whatsapp_url', 'https://wa.me/970591234567')
            ->assertJsonPath('data.0.has_account', true);
    }
}
