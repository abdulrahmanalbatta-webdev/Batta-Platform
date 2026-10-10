<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateOwnerCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_verified_owner_with_given_password(): void
    {
        $this->artisan('app:create-owner', ['email' => 'admin@batta.dev', 'name' => 'عبدالرحمن البطة'])
            ->expectsQuestion('Password', 'Secret123')
            ->assertSuccessful();

        $owner = User::where('email', 'admin@batta.dev')->sole();
        $this->assertSame(Role::Owner, $owner->role);
        $this->assertFalse($owner->isPending());
        $this->assertTrue(Hash::check('Secret123', $owner->password));
    }

    public function test_refuses_second_owner(): void
    {
        User::factory()->owner()->create();

        $this->artisan('app:create-owner', ['email' => 'another@batta.dev', 'name' => 'آخر'])
            ->expectsOutput('The dashboard already has an owner.')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'another@batta.dev']);
    }

    public function test_rejects_weak_password(): void
    {
        $this->artisan('app:create-owner', ['email' => 'admin@batta.dev', 'name' => 'عبدالرحمن'])
            ->expectsQuestion('Password', 'weak')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'admin@batta.dev']);
    }

    public function test_a_password_can_be_generated_where_nobody_types_one(): void
    {
        $this->artisan('app:create-owner', ['email' => 'admin@batta.dev', 'name' => 'عبدالرحمن البطة', '--generate-password' => true])
            ->expectsOutputToContain('One-time password: ')
            ->assertSuccessful();

        $this->assertSame('owner', User::query()->sole()->role->value);
    }
}
