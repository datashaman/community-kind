<?php

namespace App\Data;

readonly class OrganisationPermissions
{
    public function __construct(
        public bool $canUpdateOrganisation,
        public bool $canDeleteOrganisation,
        public bool $canAddMember,
        public bool $canUpdateMember,
        public bool $canRemoveMember,
        public bool $canCreateInvitation,
        public bool $canCancelInvitation,
    ) {
        //
    }
}
