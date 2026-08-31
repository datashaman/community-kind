<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\EventRegistrationStatus;
use Database\Factories\EventRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $community_event_id
 * @property int $party_id
 * @property EventRegistrationStatus $status
 * @property int $version
 * @property Carbon|null $reminded_at
 * @property Carbon|null $attended_at
 * @property Carbon|null $followed_up_at
 * @property-read Party $party
 * @property-read CommunityEvent $event
 * @property-read SupporterRegistration $registration
 */
#[Fillable(['organisation_id', 'community_event_id', 'party_id', 'supporter_registration_id', 'status', 'version', 'registered_at', 'reminded_at', 'attended_at', 'followed_up_at', 'transitioned_by_user_id'])]
class EventRegistration extends Model
{
    /** @use HasFactory<EventRegistrationFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    /** @return BelongsTo<CommunityEvent, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(CommunityEvent::class, 'community_event_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<SupporterRegistration, $this> */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(SupporterRegistration::class, 'supporter_registration_id');
    }

    protected function casts(): array
    {
        return ['status' => EventRegistrationStatus::class, 'version' => 'integer', 'registered_at' => 'datetime', 'reminded_at' => 'datetime', 'attended_at' => 'datetime', 'followed_up_at' => 'datetime'];
    }
}
