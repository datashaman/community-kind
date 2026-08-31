<?php

namespace App\Data;

use App\Models\BillingInvitation;

final readonly class IssuedBillingInvitation
{
    public function __construct(public BillingInvitation $invitation, public string $token) {}
}
