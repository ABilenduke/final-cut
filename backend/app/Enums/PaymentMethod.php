<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case GiftCard = 'gift_card';
    case Mixed = 'mixed';

    // Walk-up / point-of-sale methods (admin-v2 Plan 05). Money is collected
    // at the counter, never through Stripe — these bookings carry no
    // PaymentIntent. `PosCard` means the physical terminal took the card.
    case Cash = 'cash';
    case Comp = 'comp';
    case PosCard = 'pos_card';

    /** @return list<self> Methods a walk-up sale may record. */
    public static function posMethods(): array
    {
        return [self::Cash, self::Comp, self::PosCard];
    }
}
