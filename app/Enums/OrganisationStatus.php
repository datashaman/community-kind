<?php

namespace App\Enums;

enum OrganisationStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Archived = 'archived';
    case ScheduledForDeletion = 'scheduled_for_deletion';
    case Deleted = 'deleted';

    /**
     * @return array<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Active, self::ScheduledForDeletion],
            self::Active => [self::Archived],
            self::Archived => [self::Active, self::ScheduledForDeletion],
            self::ScheduledForDeletion => [self::Archived, self::Deleted],
            self::Deleted => [],
        };
    }

    public function accessFor(OrganisationAccessScope $scope): OrganisationAccessLevel
    {
        return match ($this) {
            self::Active => OrganisationAccessLevel::Full,
            self::Pending, self::Archived, self::ScheduledForDeletion => $scope === OrganisationAccessScope::Staff
                ? OrganisationAccessLevel::RecoveryOnly
                : OrganisationAccessLevel::Denied,
            self::Deleted => OrganisationAccessLevel::Denied,
        };
    }
}
