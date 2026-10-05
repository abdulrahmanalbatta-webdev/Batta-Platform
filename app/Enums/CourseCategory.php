<?php

namespace App\Enums;

enum CourseCategory: string
{
    case Frontend = 'frontend';
    case Backend = 'backend';
    case FullStack = 'full-stack';
    case Freelancing = 'freelancing';

    public function label(): string
    {
        return match ($this) {
            self::Frontend => 'تطوير الواجهات',
            self::Backend => 'تطوير الخلفية',
            self::FullStack => 'Full-stack',
            self::Freelancing => 'العمل الحر',
        };
    }
}
