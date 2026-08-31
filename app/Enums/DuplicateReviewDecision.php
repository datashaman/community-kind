<?php

namespace App\Enums;

enum DuplicateReviewDecision: string
{
    case Pending = 'pending';
    case Dismissed = 'dismissed';
    case Merged = 'merged';
}
