<?php

namespace App\Enums;

enum TeamStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Archived = 'archived';
    case ScheduledForDeletion = 'scheduled_for_deletion';
}
