<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use App\Enums\EligibilityStatus;
use App\Enums\IntakeStatus;
use App\Enums\IntakeUrgency;
use Database\Factories\IntakeRequestFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property int $program_id
 * @property int $party_id
 * @property ClassifiedValue $encrypted_content
 * @property array<string, mixed> $eligibility_context
 * @property EligibilityStatus $eligibility_status
 * @property IntakeStatus $status
 * @property IntakeUrgency $urgency
 * @property list<string> $risk_flags
 * @property int $version
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property-read Organisation $organisation
 * @property-read Program $program
 * @property-read Party $party
 * @property-read ServiceCase|null $serviceCase
 */
class IntakeRequest extends Model
{
    /** @use HasFactory<IntakeRequestFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return HasMany<IntakeTransition, $this> */
    public function transitions(): HasMany
    {
        return $this->hasMany(IntakeTransition::class);
    }

    /** @return HasOne<ServiceCase, $this> */
    public function serviceCase(): HasOne
    {
        return $this->hasOne(ServiceCase::class);
    }

    /** @return HasMany<PartyDuplicateReview, $this> */
    public function duplicateReviews(): HasMany
    {
        return $this->hasMany(PartyDuplicateReview::class);
    }

    protected function casts(): array
    {
        return [
            'encrypted_content' => ClassifiedValueCast::class.':intake_request',
            'eligibility_context' => 'array',
            'eligibility_status' => EligibilityStatus::class,
            'status' => IntakeStatus::class,
            'urgency' => IntakeUrgency::class,
            'risk_flags' => 'array',
        ];
    }
}
