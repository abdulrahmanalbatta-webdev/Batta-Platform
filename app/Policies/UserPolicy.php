<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

class UserPolicy
{
    /**
     * Every dashboard member can see who is on the team.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Invite a new member.
     */
    public function create(User $user): bool
    {
        return $user->role->canManageTeam();
    }

    /**
     * Change another member's role.
     */
    public function update(User $user, User $member): bool
    {
        return $this->manages($user, $member);
    }

    /**
     * Remove another member from the team.
     */
    public function delete(User $user, User $member): bool
    {
        return $this->manages($user, $member);
    }

    /**
     * Owners manage everyone but themselves; admins manage only the roles they can assign.
     */
    private function manages(User $user, User $member): bool
    {
        if ($user->is($member) || $member->role === Role::Owner) {
            return false;
        }

        return in_array($member->role, $user->role->assignableRoles(), true);
    }
}
