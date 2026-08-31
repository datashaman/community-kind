<?php

namespace App\Actions\CaseDelivery;

use App\Enums\OrganisationRole;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use LogicException;

class EnsureCanManageCase
{
    public function __construct(private readonly OrganisationContext $context) {}

    public function handle(ServiceCase $case, User $actor): void
    {
        $this->context->ensureOwns($case->organisation_id);

        if ($actor->hasOrganisationRole($case->organisation, OrganisationRole::ProgramManager, $case->program)
            && $actor->hasProgramAccess($case->program)) {
            return;
        }

        $membership = $actor->organisationMembership($case->organisation);
        if ($membership !== null
            && ! $membership->isHeld()
            && $actor->hasOrganisationRole($case->organisation, OrganisationRole::CaseWorker, $case->program)
            && $actor->hasProgramAccess($case->program)
            && $case->assignments()->where('membership_id', $membership->id)->where('status', 'active')->exists()) {
            return;
        }

        throw new LogicException('The actor is not authorised to manage this Case.');
    }
}
