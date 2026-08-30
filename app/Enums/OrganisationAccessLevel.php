<?php

namespace App\Enums;

enum OrganisationAccessLevel: string
{
    case Full = 'full';
    case ReadOnly = 'read_only';
    case RecoveryOnly = 'recovery_only';
    case Denied = 'denied';

    public function rank(): int
    {
        return match ($this) {
            self::Full => 0,
            self::ReadOnly => 1,
            self::RecoveryOnly => 2,
            self::Denied => 3,
        };
    }

    public function isAtMost(self $maximum): bool
    {
        return $this->rank() <= $maximum->rank();
    }
}
