<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Editor = 'editor';
    case Support = 'support';
    case Accountant = 'accountant';

    /**
     * The Arabic name shown in the dashboard.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'مالك',
            self::Admin => 'مدير',
            self::Editor => 'محرر محتوى',
            self::Support => 'دعم فني',
            self::Accountant => 'محاسب',
        };
    }

    public function canManageTeam(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * Courses, workshops, articles and tools: support and accountants only read them.
     */
    public function canManageContent(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Editor], true);
    }

    /**
     * Suspending, reactivating and emailing students.
     */
    public function canManageStudents(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Support], true);
    }

    /**
     * Confirming payments, refunds and coupons.
     */
    public function canManageSales(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Accountant], true);
    }

    /**
     * Replying to conversations, marking them read and deleting them.
     */
    public function canAnswerMessages(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Support], true);
    }

    /**
     * Publishing, hiding, replying to and deleting course reviews.
     */
    public function canModerateReviews(): bool
    {
        return in_array($this, [self::Owner, self::Admin, self::Editor, self::Support], true);
    }

    /**
     * Adding, moving and deleting project requests.
     */
    public function canManageLeads(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * The platform settings: general, payments, email and security.
     */
    public function canManageSettings(): bool
    {
        return in_array($this, [self::Owner, self::Admin], true);
    }

    /**
     * Exporting or wiping all of the platform's data.
     */
    public function canManagePlatformData(): bool
    {
        return $this === self::Owner;
    }

    /**
     * Roles this role may give to other members: only the owner appoints admins, and nobody appoints an owner.
     *
     * @return list<self>
     */
    public function assignableRoles(): array
    {
        return match ($this) {
            self::Owner => [self::Admin, self::Editor, self::Support, self::Accountant],
            self::Admin => [self::Editor, self::Support, self::Accountant],
            default => [],
        };
    }
}
