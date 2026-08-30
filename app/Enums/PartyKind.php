<?php

namespace App\Enums;

enum PartyKind: string
{
    case Person = 'person';
    case Household = 'household';
    case Organisation = 'organisation';
}
