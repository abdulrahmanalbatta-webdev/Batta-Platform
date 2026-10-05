<?php

namespace App\Enums;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Scheduled => 'مجدول',
            self::Published => 'منشور',
        };
    }
}
