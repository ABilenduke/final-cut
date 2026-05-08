<?php

namespace App\Enums;

enum GiftCardDeliveryMethod: string implements HasLabel
{
    case Email = 'email';
    case Print = 'print';

    public static function default(): self
    {
        return self::Email;
    }

    public function label(): string
    {
        return match ($this) {
            self::Email => 'By email',
            self::Print => 'Printed & posted',
        };
    }
}
