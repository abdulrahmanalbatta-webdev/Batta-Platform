<?php

namespace App\Enums;

/**
 * The columns of the project-requests board, in order.
 */
enum LeadStage: string
{
    case New = 'new';
    case Contacted = 'contacted';
    case Proposal = 'proposal';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'جديد',
            self::Contacted => 'تم التواصل',
            self::Proposal => 'عرض مُرسل',
            self::Won => 'مقبول',
            self::Lost => 'مرفوض',
        };
    }

    /**
     * Won and lost requests are decided; the rest are still open.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Won, self::Lost], true);
    }
}
