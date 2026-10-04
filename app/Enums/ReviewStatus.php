<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Published = 'published';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار المراجعة',
            self::Published => 'منشور',
            self::Hidden => 'مخفي',
        };
    }
}
