<?php

namespace App\Enums;

enum SecurityIncidentStatus: string
{
    case Reported = 'reported';
    case Triaging = 'triaging';
    case Confirmed = 'confirmed';
    case Contained = 'contained';
    case Eradicated = 'eradicated';
    case Recovering = 'recovering';
    case Monitoring = 'monitoring';
    case Closed = 'closed';
    case FalsePositive = 'false_positive';
    case Reopened = 'reopened';

    public function allowsContainment(): bool
    {
        return ! in_array($this, [self::Closed, self::FalsePositive], true);
    }
}
