<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\PartnerCommitmentStatus;
use Database\Factories\PartnerCommitmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $partner_profile_id
 * @property string $title
 * @property string $details
 * @property PartnerCommitmentStatus $status
 * @property Carbon|null $due_on
 * @property-read PartnerProfile $partner
 */
#[Fillable(['organisation_id', 'partner_profile_id', 'title', 'details', 'status', 'due_on', 'completed_at', 'recorded_by_user_id'])]
class PartnerCommitment extends Model
{
    /** @use HasFactory<PartnerCommitmentFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    /** @return BelongsTo<PartnerProfile, $this> */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(PartnerProfile::class, 'partner_profile_id');
    }

    protected function casts(): array
    {
        return ['status' => PartnerCommitmentStatus::class, 'due_on' => 'date', 'completed_at' => 'datetime'];
    }
}
