<?php

namespace Tests\Feature\Api;

use App\Enums\Role;
use App\Models\User;
use App\Notifications\EmailChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_own_details(): void
    {
        $member = User::factory()->create();

        $response = $this->actingAs($member)->putJson(route('api.profile.update'), [
            'name' => 'سارة النجار',
            'email' => 'Sara@Batta.dev',
            'title' => 'محررة المحتوى',
            'phone' => '+970 59 000 0000',
            'bio' => 'نبذة قصيرة',
            'github' => 'sara-dev',
            'linkedin' => 'sara',
            'current_password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'sara@batta.dev')
            ->assertJsonPath('data.title', 'محررة المحتوى');
        $this->assertDatabaseHas('users', ['id' => $member->id, 'name' => 'سارة النجار', 'email' => 'sara@batta.dev', 'github' => 'sara-dev']);
    }

    public function test_changing_the_email_needs_the_password_and_tells_the_old_address(): void
    {
        Notification::fake();
        $member = User::factory()->create(['email' => 'old@batta.dev']);

        $this->actingAs($member)->putJson(route('api.profile.update'), ['name' => $member->name, 'email' => 'new@batta.dev'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['current_password' => 'حقل كلمة المرور الحالية مطلوب.']);
        $this->actingAs($member)->putJson(route('api.profile.update'), ['name' => $member->name, 'email' => 'new@batta.dev', 'current_password' => 'wrong'])
            ->assertJsonValidationErrors('current_password');

        $this->actingAs($member)->putJson(route('api.profile.update'), ['name' => $member->name, 'email' => 'new@batta.dev', 'current_password' => 'password'])->assertOk();

        Notification::assertSentOnDemand(EmailChanged::class, fn (EmailChanged $notice, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'old@batta.dev');
    }

    public function test_other_details_change_without_the_password(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->putJson(route('api.profile.update'), ['name' => 'اسم جديد', 'email' => $member->email])->assertOk();
    }

    public function test_cannot_change_own_role_through_profile(): void
    {
        $member = User::factory()->create();

        $this->actingAs($member)->putJson(route('api.profile.update'), [
            'name' => $member->name,
            'email' => $member->email,
            'role' => Role::Owner->value,
        ])->assertOk();

        $this->assertSame(Role::Editor, $member->fresh()->role);
    }

    public function test_email_taken_by_another_member_returns_422(): void
    {
        User::factory()->create(['email' => 'taken@batta.dev']);
        $member = User::factory()->create();

        $response = $this->actingAs($member)->putJson(route('api.profile.update'), ['name' => $member->name, 'email' => 'taken@batta.dev']);

        $response->assertUnprocessable()->assertJsonValidationErrors(['email' => 'البريد الإلكتروني مستخدم مسبقاً.']);
    }

    public function test_invalid_github_handle_returns_422(): void
    {
        $member = User::factory()->create();

        $response = $this->actingAs($member)->putJson(route('api.profile.update'), [
            'name' => $member->name,
            'email' => $member->email,
            'github' => 'https://github.com/sara',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['github' => 'صيغة حقل حساب GitHub غير صالحة.']);
    }

    public function test_empty_payload_returns_422_for_name_and_email(): void
    {
        $response = $this->actingAs(User::factory()->create())->putJson(route('api.profile.update'), []);

        $response->assertUnprocessable()->assertJsonValidationErrors(['name', 'email']);
    }
}
