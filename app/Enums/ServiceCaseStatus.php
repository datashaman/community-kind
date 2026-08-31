<?php

namespace App\Enums;

enum ServiceCaseStatus: string
{
    case Open = 'open';
    case Active = 'active';
    case OnHold = 'on_hold';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
