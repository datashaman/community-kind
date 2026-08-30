<?php

namespace App\Enums;

enum OrganisationStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Archived = 'archived';
    case ScheduledForDeletion = 'scheduled_for_deletion';
}
