<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\VolunteerOpportunityStatus;
use Database\Factories\VolunteerOpportunityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $capacity
 * @property VolunteerOpportunityStatus $status
 * @property Carbon $registration_opens_at
 * @property Carbon $registration_closes_at
 */
#[Fillable(['organisation_id', 'title', 'summary', 'interest_tags', 'capacity', 'status', 'registration_opens_at', 'registration_closes_at', 'starts_at', 'ends_at', 'published_at', 'created_by_user_id'])]
class VolunteerOpportunity extends Model
{
    /** @use HasFactory<VolunteerOpportunityFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    public function acceptsRegistrations(): bool
    {
        return $this->status === VolunteerOpportunityStatus::Published
            && $this->registration_opens_at->isPast()
            && $this->registration_closes_at->isFuture()
            && $this->applications()->whereNotIn('status', ['rejected', 'withdrawn'])->count() < $this->capacity;
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<VolunteerApplication, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(VolunteerApplication::class);
    }

    /** @return HasMany<VolunteerShift, $this> */
    public function shifts(): HasMany
    {
        return $this->hasMany(VolunteerShift::class);
    }

    protected function casts(): array
    {
        return ['interest_tags' => 'array', 'capacity' => 'integer', 'status' => VolunteerOpportunityStatus::class, 'registration_opens_at' => 'datetime', 'registration_closes_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'published_at' => 'datetime'];
    }
}
