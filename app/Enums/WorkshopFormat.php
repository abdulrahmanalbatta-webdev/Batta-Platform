<?php

namespace App\Enums;

enum WorkshopFormat: string
{
    case Online = 'online';
    case InPerson = 'in-person';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'أونلاين',
            self::InPerson => 'حضوري',
        };
    }
}
