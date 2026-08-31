<?php

namespace App\Enums;

enum SecurityIncidentClassification: string
{
    case Alert = 'alert';
    case Incident = 'incident';
}
