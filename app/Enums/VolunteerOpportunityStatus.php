<?php

namespace App\Enums;

enum VolunteerOpportunityStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
