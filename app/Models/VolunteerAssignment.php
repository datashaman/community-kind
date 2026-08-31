<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\VolunteerAssignmentStatus;
use Database\Factories\VolunteerAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property int $party_id
 * @property VolunteerAssignmentStatus $status
 * @property int $version
 * @property Carbon|null $attended_at
 * @property-read Party $party
 * @property-read VolunteerShift $shift
 */
#[Fillable(['organisation_id', 'volunteer_shift_id', 'volunteer_application_id', 'party_id', 'status', 'version', 'confirmed_at', 'cancelled_at', 'attended_at', 'transitioned_by_user_id'])]
class VolunteerAssignment extends Model
{
    /** @use HasFactory<VolunteerAssignmentFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    /** @return BelongsTo<VolunteerShift, $this> */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(VolunteerShift::class, 'volunteer_shift_id');
    }

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

    /** @return HasOne<VolunteerHourEntry, $this> */
    public function hours(): HasOne
    {
        return $this->hasOne(VolunteerHourEntry::class);
    }

    protected function casts(): array
    {
        return ['status' => VolunteerAssignmentStatus::class, 'version' => 'integer', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime', 'attended_at' => 'datetime'];
    }
}
