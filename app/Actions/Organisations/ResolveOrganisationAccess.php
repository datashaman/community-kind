<?php

namespace App\Actions\Organisations;

use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationStatus;
use App\Models\Organisation;
use App\Models\User;

class ResolveOrganisationAccess
{
    public function handle(Organisation $organisation, OrganisationAccessScope $scope): OrganisationAccessLevel
    {
        $access = $organisation->status->accessFor($scope);
        $now = now();

        $holds = $organisation->accessHolds()
            ->whereNull('released_at')
            ->where('starts_at', '<=', $now)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', $now))
            ->whereIn('scope', [OrganisationAccessScope::All->value, $scope->value])
            ->get();

        foreach ($holds as $hold) {
            if ($hold->access_level->rank() > $access->rank()) {
                $access = $hold->access_level;
            }
        }

        return $access;
    }

    public function allowsStaffCapability(Organisation $organisation, ?User $user, string $capability): bool
    {
        $access = $this->handle($organisation, OrganisationAccessScope::Staff);
        $isAdministrator = $user?->ownsOrganisation($organisation)
            || $user?->hasOrganisationRole($organisation, OrganisationRole::OrganisationAdministrator);

        return match ($capability) {
            'full' => $access === OrganisationAccessLevel::Full,
            'administration' => $access === OrganisationAccessLevel::Full
                || ($organisation->status === OrganisationStatus::Pending
                    && $access === OrganisationAccessLevel::RecoveryOnly
                    && $isAdministrator),
            'recovery' => $access->isAtMost(OrganisationAccessLevel::ReadOnly)
                || ($access === OrganisationAccessLevel::RecoveryOnly && $isAdministrator),
            'membership' => $access->isAtMost(OrganisationAccessLevel::RecoveryOnly),
            default => $access->isAtMost(OrganisationAccessLevel::ReadOnly),
        };
    }
}
