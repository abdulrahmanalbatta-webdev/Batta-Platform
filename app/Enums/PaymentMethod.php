<?php

namespace App\Enums;

/**
 * How a student paid. Payments are taken by hand (no card gateway reaches Gaza): the team confirms them on the order.
 */
enum PaymentMethod: string
{
    case BankTransfer = 'bank-transfer';
    case Wallet = 'wallet';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'تحويل بنكي',
            self::Wallet => 'محفظة إلكترونية',
            self::Cash => 'نقداً',
        };
    }
}
