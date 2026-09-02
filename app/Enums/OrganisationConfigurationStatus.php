<?php

namespace App\Enums;

enum OrganisationConfigurationStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Superseded = 'superseded';
    case Retired = 'retired';
}
