<?php

namespace App\Enums;

/**
 * What the bell tells the team about. Each type goes to the roles that act on it, and each member chooses whether it also comes by email.
 */
enum AlertType: string
{
    case Orders = 'orders';
    case Leads = 'leads';
    case Reviews = 'reviews';
    case Messages = 'messages';

    /**
     * Whether members of this role receive this alert.
     */
    public function isFor(Role $role): bool
    {
        return match ($this) {
            self::Orders => $role->canManageSales(),
            self::Leads => $role->canManageLeads(),
            self::Reviews => $role->canModerateReviews(),
            self::Messages => $role->canAnswerMessages(),
        };
    }

    /**
     * Email is on by default for everything but messages, which can be many.
     */
    public function emailsByDefault(): bool
    {
        return $this !== self::Messages;
    }

    /**
     * @return list<Role>
     */
    public function roles(): array
    {
        return array_values(array_filter(Role::cases(), fn (Role $role): bool => $this->isFor($role)));
    }
}
