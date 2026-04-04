<?php

namespace App\Enums;

enum SeatType: string
{
    case Standard = 'standard';
    case Premium = 'premium';
    case Accessible = 'accessible';
}
