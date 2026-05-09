<?php

namespace App\Enums;

enum GiftCardEdition: string implements HasLabel
{
    case Reactor = 'reactor';
    case CharterGold = 'gold';
    case PureVoid = 'void';

    public static function default(): self
    {
        return self::Reactor;
    }

    public function label(): string
    {
        return match ($this) {
            self::Reactor => 'Reactor',
            self::CharterGold => 'Charter Gold',
            self::PureVoid => 'Pure Void',
        };
    }
}
