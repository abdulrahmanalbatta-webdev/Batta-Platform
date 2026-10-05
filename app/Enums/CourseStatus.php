<?php

namespace App\Enums;

enum CourseStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Review => 'قيد المراجعة',
            self::Published => 'منشورة',
        };
    }
}
