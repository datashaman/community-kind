<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\OrganisationRole;
use Database\Factories\OrganisationInvitationRoleAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $organisation_invitation_id
 * @property OrganisationRole $role
 * @property int|null $program_id
 * @property-read OrganisationInvitation $invitation
 * @property-read Program|null $program
 */
#[Fillable(['organisation_id', 'organisation_invitation_id', 'role', 'program_id'])]
class OrganisationInvitationRoleAssignment extends Model
{
    /** @use HasFactory<OrganisationInvitationRoleAssignmentFactory> */
    use BelongsToOrganisation, HasFactory;

    /** @return BelongsTo<OrganisationInvitation, $this> */
    public function invitation(): BelongsTo
    {
        return $this->belongsTo(OrganisationInvitation::class, 'organisation_invitation_id');
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['role' => OrganisationRole::class];
    }
}
