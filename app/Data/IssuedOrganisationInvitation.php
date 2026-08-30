<?php

namespace App\Data;

use App\Models\OrganisationInvitation;

class IssuedOrganisationInvitation
{
    public function __construct(
        public readonly OrganisationInvitation $invitation,
        public readonly string $token,
    ) {}
}
