<?php

namespace App\Enums;

enum VolunteerShiftStatus: string
{
    case Open = 'open';
    case Cancelled = 'cancelled';
    case Completed = 'completed';
}
