<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Card = 'card';
    case GiftCard = 'gift_card';
    case Mixed = 'mixed';
}
