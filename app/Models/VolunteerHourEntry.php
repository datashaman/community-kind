<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\VolunteerHourEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/** @property string $id */
#[Fillable(['organisation_id', 'volunteer_assignment_id', 'party_id', 'minutes', 'occurred_at', 'recorded_by_user_id'])]
class VolunteerHourEntry extends Model
{
    /** @use HasFactory<VolunteerHourEntryFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Volunteer hour entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Volunteer hour entries are append-only.'));
    }

    /** @return BelongsTo<VolunteerAssignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(VolunteerAssignment::class, 'volunteer_assignment_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    protected function casts(): array
    {
        return ['minutes' => 'integer', 'occurred_at' => 'datetime'];
    }
}
