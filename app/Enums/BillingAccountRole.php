<?php

namespace App\Enums;

enum BillingAccountRole: string
{
    case Administrator = 'administrator';
    case Viewer = 'viewer';
}
