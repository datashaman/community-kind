<?php

namespace App\Enums;

enum SecurityIncidentSeverity: string
{
    case S1Critical = 's1_critical';
    case S2High = 's2_high';
    case S3Medium = 's3_medium';
    case S4Low = 's4_low';
}
