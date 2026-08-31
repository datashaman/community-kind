<?php

namespace App\Enums;

enum EligibilityStatus: string
{
    case NeedsReview = 'needs_review';
    case Eligible = 'eligible';
    case Ineligible = 'ineligible';
}
