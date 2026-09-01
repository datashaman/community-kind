<?php

namespace App\Enums;

enum BillingAccountStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
}
