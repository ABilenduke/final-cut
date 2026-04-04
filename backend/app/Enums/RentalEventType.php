<?php

namespace App\Enums;

enum RentalEventType: string
{
    case Birthday = 'birthday';
    case Corporate = 'corporate';
    case Proposal = 'proposal';
    case Custom = 'custom';
}
