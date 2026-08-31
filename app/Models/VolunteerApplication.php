<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\VolunteerApplicationStatus;
use App\Enums\VolunteerOnboardingStatus;
use Database\Factories\VolunteerApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $volunteer_opportunity_id
 * @property int $party_id
 * @property VolunteerApplicationStatus $status
 * @property VolunteerOnboardingStatus $onboarding_status
 * @property int $version
 * @property-read Party $party
 * @property-read SupporterRegistration $registration
 */
#[Fillable(['organisation_id', 'volunteer_opportunity_id', 'party_id', 'supporter_registration_id', 'status', 'onboarding_status', 'interests', 'availability', 'follow_up_status', 'version', 'submitted_at', 'reviewed_at', 'reviewed_by_user_id'])]
class VolunteerApplication extends Model
{
    /** @use HasFactory<VolunteerApplicationFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    /** @return BelongsTo<VolunteerOpportunity, $this> */
    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(VolunteerOpportunity::class, 'volunteer_opportunity_id');
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

    /** @return HasMany<VolunteerCredential, $this> */
    public function credentials(): HasMany
    {
        return $this->hasMany(VolunteerCredential::class);
    }

    /** @return HasMany<VolunteerAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(VolunteerAssignment::class);
    }

    protected function casts(): array
    {
        return ['status' => VolunteerApplicationStatus::class, 'onboarding_status' => VolunteerOnboardingStatus::class, 'interests' => 'array', 'availability' => 'array', 'version' => 'integer', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }
}
