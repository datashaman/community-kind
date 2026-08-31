<?php

namespace App\Enums;

enum IntakeUrgency: string
{
    case Routine = 'routine';
    case Priority = 'priority';
    case Urgent = 'urgent';
}
