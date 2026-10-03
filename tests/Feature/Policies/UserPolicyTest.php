<?php

namespace Tests\Feature\Policies;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Who may change or remove whom.
     *
     * @return array<string, array{Role, Role, bool}>
     */
    public static function managementMatrix(): array
    {
        return [
            'owner manages admin' => [Role::Owner, Role::Admin, true],
            'owner manages editor' => [Role::Owner, Role::Editor, true],
            'owner manages support' => [Role::Owner, Role::Support, true],
            'owner manages accountant' => [Role::Owner, Role::Accountant, true],
            'owner cannot manage another owner' => [Role::Owner, Role::Owner, false],
            'admin manages editor' => [Role::Admin, Role::Editor, true],
            'admin manages support' => [Role::Admin, Role::Support, true],
            'admin manages accountant' => [Role::Admin, Role::Accountant, true],
            'admin cannot manage another admin' => [Role::Admin, Role::Admin, false],
            'admin cannot manage owner' => [Role::Admin, Role::Owner, false],
            'editor cannot manage editor' => [Role::Editor, Role::Editor, false],
            'support cannot manage accountant' => [Role::Support, Role::Accountant, false],
            'accountant cannot manage support' => [Role::Accountant, Role::Support, false],
        ];
    }

    #[DataProvider('managementMatrix')]
    public function test_management_follows_role_matrix(Role $actorRole, Role $memberRole, bool $allowed): void
    {
        $actor = User::factory()->role($actorRole)->create();
        $member = User::factory()->role($memberRole)->create();

        $this->assertSame($allowed, $actor->can('update', $member));
        $this->assertSame($allowed, $actor->can('delete', $member));
    }

    #[DataProvider('roles')]
    public function test_nobody_manages_themselves(Role $role): void
    {
        $member = User::factory()->role($role)->create();

        $this->assertFalse($member->can('update', $member));
        $this->assertFalse($member->can('delete', $member));
    }

    #[DataProvider('roles')]
    public function test_only_owner_and_admin_invite(Role $role): void
    {
        $member = User::factory()->role($role)->create();

        $this->assertSame(in_array($role, [Role::Owner, Role::Admin], true), $member->can('create', User::class));
    }

    /**
     * @return array<string, array{Role}>
     */
    public static function roles(): array
    {
        return collect(Role::cases())->mapWithKeys(fn (Role $role) => [$role->value => [$role]])->all();
    }
}
