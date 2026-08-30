<?php

namespace App\Data;

use App\Models\TeamInvitation;

class IssuedTeamInvitation
{
    public function __construct(
        public readonly TeamInvitation $invitation,
        public readonly string $token,
    ) {}
}
