<?php

namespace App\Enums;

enum CalendarEventType: string
{
    case Showtime = 'showtime';
    case SpecialEvent = 'special_event';
    case LoyaltyExclusive = 'loyalty_exclusive';
    case PrivateScreeningBlackout = 'private_screening_blackout';
}
