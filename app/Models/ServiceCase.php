<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\CaseClassification;
use App\Enums\ServiceCaseStatus;
use Database\Factories\ServiceCaseFactory;
use Illuminate\Database\Eloquent\Collection;
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
 * @property string $intake_request_id
 * @property int $program_id
 * @property int $party_id
 * @property ServiceCaseStatus $status
 * @property int $version
 * @property CaseClassification $confidentiality
 * @property Carbon $opened_at
 * @property Carbon|null $closed_at
 * @property string|null $closure_reason
 * @property Carbon|null $follow_up_at
 * @property array<string, bool>|null $closure_checklist
 * @property-read Organisation $organisation
 * @property-read Program $program
 * @property-read Party $party
 * @property-read CaseOutcome|null $outcome
 * @property-read Collection<int, CaseDocument> $documents
 */
class ServiceCase extends Model
{
    /** @use HasFactory<ServiceCaseFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<IntakeRequest, $this> */
    public function intakeRequest(): BelongsTo
    {
        return $this->belongsTo(IntakeRequest::class);
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

    /** @return HasMany<CaseAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(CaseAssignment::class);
    }

    /** @return HasMany<CaseGoal, $this> */
    public function goals(): HasMany
    {
        return $this->hasMany(CaseGoal::class);
    }

    /** @return HasMany<CaseService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(CaseService::class);
    }

    /** @return HasMany<ExternalReferral, $this> */
    public function referrals(): HasMany
    {
        return $this->hasMany(ExternalReferral::class);
    }

    /** @return HasMany<CaseTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(CaseTask::class);
    }

    /** @return HasMany<CaseAppointment, $this> */
    public function appointments(): HasMany
    {
        return $this->hasMany(CaseAppointment::class);
    }

    /** @return HasMany<CaseInteraction, $this> */
    public function interactions(): HasMany
    {
        return $this->hasMany(CaseInteraction::class);
    }

    /** @return HasMany<CaseNote, $this> */
    public function notes(): HasMany
    {
        return $this->hasMany(CaseNote::class);
    }

    /** @return HasOne<CaseOutcome, $this> */
    public function outcome(): HasOne
    {
        return $this->hasOne(CaseOutcome::class);
    }

    /** @return HasMany<CaseWorkflowTransition, $this> */
    public function workflowTransitions(): HasMany
    {
        return $this->hasMany(CaseWorkflowTransition::class);
    }

    /** @return HasMany<RestrictedAccessGrant, $this> */
    public function restrictedAccessGrants(): HasMany
    {
        return $this->hasMany(RestrictedAccessGrant::class);
    }

    /** @return HasMany<CaseRiskAssessment, $this> */
    public function riskAssessments(): HasMany
    {
        return $this->hasMany(CaseRiskAssessment::class);
    }

    /** @return HasMany<CaseDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(CaseDocument::class);
    }

    protected function casts(): array
    {
        return [
            'status' => ServiceCaseStatus::class,
            'confidentiality' => CaseClassification::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'closure_checklist' => 'array',
        ];
    }
}
