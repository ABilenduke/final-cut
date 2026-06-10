<?php

namespace App\Enums;

enum GiftCardLedgerType: string
{
    case Purchase = 'purchase';
    case Redemption = 'redemption';
    case Refund = 'refund';
    case Void = 'void';
    case Adjustment = 'adjustment';
}
