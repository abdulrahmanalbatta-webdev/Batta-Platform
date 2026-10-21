<?php

namespace App\Enums;

/**
 * The steps of a mentorship request, in order, then the ways it can end early.
 */
enum MentorshipStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case ProofSubmitted = 'proof_submitted';
    case Paid = 'paid';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'جديد',
            self::Accepted => 'بانتظار الدفع',
            self::ProofSubmitted => 'بانتظار مراجعة الدفع',
            self::Paid => 'مدفوع',
            self::Scheduled => 'تم تحديد الموعد',
            self::Completed => 'مكتمل',
            self::Rejected => 'مرفوض',
            self::Expired => 'انتهت مهلة الدفع',
        };
    }

    /**
     * Accepted and still going: each of these holds one of the month's seats.
     *
     * @return list<self>
     */
    public static function holdingSeat(): array
    {
        return [self::Accepted, self::ProofSubmitted, self::Paid, self::Scheduled];
    }

    /**
     * Not finished yet: a student with one of these can't apply again.
     *
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Pending, ...self::holdingSeat()];
    }
}
