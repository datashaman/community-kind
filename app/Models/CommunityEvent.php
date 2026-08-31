<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\CommunityEventStatus;
use Database\Factories\CommunityEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $title
 * @property int $capacity
 * @property CommunityEventStatus $status
 * @property Carbon $registration_opens_at
 * @property Carbon $registration_closes_at
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 */
#[Fillable(['organisation_id', 'title', 'summary', 'capacity', 'status', 'registration_opens_at', 'registration_closes_at', 'starts_at', 'ends_at', 'published_at', 'created_by_user_id'])]
class CommunityEvent extends Model
{
    /** @use HasFactory<CommunityEventFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    public function acceptsRegistrations(): bool
    {
        return $this->status === CommunityEventStatus::Published && $this->registration_opens_at->isPast() && $this->registration_closes_at->isFuture();
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<EventRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    protected function casts(): array
    {
        return ['status' => CommunityEventStatus::class, 'capacity' => 'integer', 'registration_opens_at' => 'datetime', 'registration_closes_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'published_at' => 'datetime'];
    }
}
