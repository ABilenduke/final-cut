<?php

namespace App\Enums;

enum GiftCardStatus: string
{
    case Active = 'active';
    case Depleted = 'depleted';
    case Expired = 'expired';
}
