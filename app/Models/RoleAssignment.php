<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\OrganisationRole;
use Database\Factories\RoleAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $membership_id
 * @property OrganisationRole $role
 * @property int|null $program_id
 * @property Carbon|null $ended_at
 * @property-read Membership $membership
 * @property-read Program|null $program
 */
#[Fillable(['organisation_id', 'membership_id', 'role', 'program_id', 'ended_at'])]
class RoleAssignment extends Model
{
    /** @use HasFactory<RoleAssignmentFactory> */
    use BelongsToOrganisation, HasFactory;

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class)->withTrashed();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => OrganisationRole::class,
            'ended_at' => 'datetime',
        ];
    }
}
