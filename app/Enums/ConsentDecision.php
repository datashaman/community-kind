<?php

namespace App\Enums;

enum ConsentDecision: string
{
    case Granted = 'granted';
    case Withdrawn = 'withdrawn';
    case Suppressed = 'suppressed';
}
