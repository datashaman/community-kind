<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\ServiceCaseStatus;
use Database\Factories\ServiceCaseFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $intake_request_id
 * @property int $program_id
 * @property int $party_id
 * @property ServiceCaseStatus $status
 * @property string $confidentiality
 * @property Carbon $opened_at
 * @property-read Organisation $organisation
 * @property-read Program $program
 * @property-read Party $party
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

    protected function casts(): array
    {
        return ['status' => ServiceCaseStatus::class, 'opened_at' => 'datetime'];
    }
}
