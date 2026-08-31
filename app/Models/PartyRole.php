<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\PartyBusinessRole;
use Database\Factories\PartyRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property PartyBusinessRole $role */
#[Fillable(['organisation_id', 'party_id', 'role'])]
class PartyRole extends Model
{
    /** @use HasFactory<PartyRoleFactory> */
    use BelongsToOrganisation, HasFactory;

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['role' => PartyBusinessRole::class];
    }
}
