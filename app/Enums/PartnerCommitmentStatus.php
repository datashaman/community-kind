<?php

namespace App\Enums;

enum PartnerCommitmentStatus: string
{
    case Planned = 'planned';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
