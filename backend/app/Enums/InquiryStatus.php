<?php

namespace App\Enums;

enum InquiryStatus: string
{
    case Pending = 'pending';
    case Contacted = 'contacted';
    case Confirmed = 'confirmed';
    case Declined = 'declined';
}
