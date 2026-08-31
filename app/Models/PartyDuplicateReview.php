<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\DuplicateReviewDecision;
use Database\Factories\PartyDuplicateReviewFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $intake_request_id
 * @property int $submitted_party_id
 * @property int $candidate_party_id
 * @property list<string> $matched_fields
 * @property DuplicateReviewDecision $decision
 * @property int|null $canonical_party_id
 * @property Carbon|null $decided_at
 * @property Carbon|null $reversed_at
 * @property int|null $decided_by_user_id
 * @property int|null $reversed_by_user_id
 * @property-read IntakeRequest $intakeRequest
 * @property-read Party $submittedParty
 * @property-read Party $candidateParty
 */
class PartyDuplicateReview extends Model
{
    /** @use HasFactory<PartyDuplicateReviewFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected $guarded = ['organisation_id'];

    /** @return BelongsTo<IntakeRequest, $this> */
    public function intakeRequest(): BelongsTo
    {
        return $this->belongsTo(IntakeRequest::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function submittedParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'submitted_party_id');
    }

    /** @return BelongsTo<Party, $this> */
    public function candidateParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'candidate_party_id');
    }

    protected function casts(): array
    {
        return [
            'matched_fields' => 'array',
            'decision' => DuplicateReviewDecision::class,
            'decided_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }
}
