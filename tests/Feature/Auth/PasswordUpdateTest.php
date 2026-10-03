<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_changes_password_and_ends_other_sessions(): void
    {
        config(['session.driver' => 'database']);
        $member = User::factory()->create(['remember_token' => 'old-token']);
        DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $member->id, 'payload' => '', 'last_activity' => now()->timestamp]);

        $response = $this->actingAs($member)->putJson(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertOk();
        $member->refresh();
        $this->assertTrue(Hash::check('NewPassword1', $member->password));
        $this->assertNotSame('old-token', $member->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'other-device']);
    }

    public function test_wrong_current_password_returns_422(): void
    {
        $member = User::factory()->create();

        $response = $this->actingAs($member)->putJson(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'NewPassword1',
            'password_confirmation' => 'NewPassword1',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['current_password' => 'كلمة المرور الحالية غير صحيحة.']);
        $this->assertTrue(Hash::check('password', $member->fresh()->password));
    }

    public function test_mismatched_confirmation_returns_422(): void
    {
        $response = $this->actingAs(User::factory()->create())->putJson(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'NewPassword1',
            'password_confirmation' => 'OtherPassword1',
        ]);

        $response->assertUnprocessable()->assertJsonValidationErrors(['password' => 'تأكيد كلمة المرور غير متطابق.']);
    }
}
