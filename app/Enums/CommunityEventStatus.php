<?php

namespace App\Enums;

enum CommunityEventStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
