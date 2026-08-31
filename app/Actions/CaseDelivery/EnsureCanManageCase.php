<?php

namespace App\Actions\CaseDelivery;

use App\Authorization\CaseAccess;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use LogicException;

class EnsureCanManageCase
{
    public function __construct(
        private readonly OrganisationContext $context,
        private readonly CaseAccess $access,
    ) {}

    public function handle(ServiceCase $case, User $actor): void
    {
        $this->context->ensureOwns($case->organisation_id);

        if ($this->access->canView($actor, $case)) {
            return;
        }

        throw new LogicException('The actor is not authorised to manage this Case.');
    }
}
