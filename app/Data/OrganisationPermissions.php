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
        public bool $canTransitionOrganisation,
        public bool $canChangeOrganisationSlug,
        public bool $canTransferOwnership,
    ) {
        //
    }

    public function constrainedByAccess(bool $canAdminister, bool $canRecover): self
    {
        return new self(
            canUpdateOrganisation: $canAdminister && $this->canUpdateOrganisation,
            canDeleteOrganisation: $canRecover && $this->canDeleteOrganisation,
            canAddMember: $canAdminister && $this->canAddMember,
            canUpdateMember: $canAdminister && $this->canUpdateMember,
            canRemoveMember: $canAdminister && $this->canRemoveMember,
            canCreateInvitation: $canAdminister && $this->canCreateInvitation,
            canCancelInvitation: $canAdminister && $this->canCancelInvitation,
            canTransitionOrganisation: $canRecover && $this->canTransitionOrganisation,
            canChangeOrganisationSlug: $canAdminister && $this->canChangeOrganisationSlug,
            canTransferOwnership: $canAdminister && $this->canTransferOwnership,
        );
    }
}
