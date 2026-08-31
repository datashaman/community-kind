<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\VolunteerShiftStatus;
use Database\Factories\VolunteerShiftFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $capacity
 * @property VolunteerShiftStatus $status
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
#[Fillable(['organisation_id', 'volunteer_opportunity_id', 'title', 'starts_at', 'ends_at', 'capacity', 'status', 'created_by_user_id'])]
class VolunteerShift extends Model
{
    /** @use HasFactory<VolunteerShiftFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    /** @return BelongsTo<VolunteerOpportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class, 'volunteer_opportunity_id');
    }

    /** @return HasMany<VolunteerAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(VolunteerAssignment::class);
    }

    protected function casts(): array
    {
        return ['status' => VolunteerShiftStatus::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'capacity' => 'integer'];
    }
}
