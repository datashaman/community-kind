<?php

namespace App\Enums;

enum SupporterJourneyStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
}
