<?php

namespace App\Enums;

enum SupporterJourneyKind: string
{
    case General = 'general';
    case ReEngagement = 're_engagement';
    case Event = 'event';
    case Volunteer = 'volunteer';

    public function label(): string
    {
        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
