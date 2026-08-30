<?php

namespace App\Enums;

enum OrganisationPermission: string
{
    case UpdateOrganisation = 'organisation:update';
    case DeleteOrganisation = 'organisation:delete';

    case AddMember = 'member:add';
    case UpdateMember = 'member:update';
    case RemoveMember = 'member:remove';

    case CreateInvitation = 'invitation:create';
    case CancelInvitation = 'invitation:cancel';
}
