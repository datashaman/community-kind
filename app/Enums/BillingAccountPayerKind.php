<?php

namespace App\Enums;

enum BillingAccountPayerKind: string
{
    case Individual = 'individual';
    case Organisation = 'organisation';
}
