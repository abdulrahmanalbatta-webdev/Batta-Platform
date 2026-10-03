<?php

namespace App\Enums;

enum OrderItemType: string
{
    case Course = 'course';
    case Workshop = 'workshop';
    case ProMonth = 'pro-month';

    public function label(): string
    {
        return match ($this) {
            self::Course => 'دورة',
            self::Workshop => 'ورشة',
            self::ProMonth => 'اشتراك',
        };
    }
}
