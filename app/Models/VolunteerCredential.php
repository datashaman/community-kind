<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\VolunteerCredentialStatus;
use Database\Factories\VolunteerCredentialFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $volunteer_application_id
 * @property string $type
 * @property VolunteerCredentialStatus $status
 * @property Carbon|null $expires_at
 */
#[Fillable(['organisation_id', 'volunteer_application_id', 'party_id', 'type', 'status', 'verified_at', 'expires_at', 'recorded_by_user_id'])]
class VolunteerCredential extends Model
{
    /** @use HasFactory<VolunteerCredentialFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    /** @return BelongsTo<VolunteerApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(VolunteerApplication::class, 'volunteer_application_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function effectiveStatus(): VolunteerCredentialStatus
    {
        if ($this->status === VolunteerCredentialStatus::Verified && $this->expires_at?->isPast()) {
            return VolunteerCredentialStatus::Expired;
        }

        return $this->status;
    }

    public function expiresSoon(): bool
    {
        return $this->status === VolunteerCredentialStatus::Verified
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && $this->expires_at->lte(now()->addDays(30));
    }

    protected function casts(): array
    {
        return ['status' => VolunteerCredentialStatus::class, 'verified_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
