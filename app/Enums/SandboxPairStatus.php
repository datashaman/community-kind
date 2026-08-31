<?php

namespace App\Enums;

enum SandboxPairStatus: string
{
    case Provisioning = 'provisioning';
    case Ready = 'ready';
    case Active = 'active';
    case Expired = 'expired';
    case Purging = 'purging';
    case Purged = 'purged';
    case Failed = 'failed';

    public function isAccessible(): bool
    {
        return in_array($this, [self::Ready, self::Active], true);
    }
}
