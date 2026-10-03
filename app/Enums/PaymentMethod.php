<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case PayPal = 'paypal';
    case ApplePay = 'apple-pay';
    case BankTransfer = 'bank-transfer';

    public function label(): string
    {
        return match ($this) {
            self::Card => 'بطاقة',
            self::PayPal => 'PayPal',
            self::ApplePay => 'Apple Pay',
            self::BankTransfer => 'تحويل بنكي',
        };
    }
}
