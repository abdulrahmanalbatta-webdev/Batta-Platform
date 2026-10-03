<?php

namespace App\Enums;

enum ArticleCategory: string
{
    case Tutorials = 'tutorials';
    case BehindTheScenes = 'behind-the-scenes';
    case Freelancing = 'freelancing';
    case ToolsAndAi = 'tools-ai';

    public function label(): string
    {
        return match ($this) {
            self::Tutorials => 'دروس عملية',
            self::BehindTheScenes => 'خلف الكواليس',
            self::Freelancing => 'العمل الحر',
            self::ToolsAndAi => 'أدوات و AI',
        };
    }
}
