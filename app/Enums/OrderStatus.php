<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'معلّق',
            self::Completed => 'مكتمل',
            self::Failed => 'فشل',
            self::Refunded => 'مسترد',
        };
    }
}
