<?php

namespace App\Enums;

enum RestrictedAccessPermission: string
{
    case SensitiveData = 'sensitive_data';
    case IdentifiableCaseExport = 'identifiable_case_export';
}
