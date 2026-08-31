<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseClassification;
use Database\Factories\CaseRiskAssessmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $service_case_id
 * @property CaseClassification $classification
 * @property ClassifiedValue $encrypted_content
 * @property Carbon $effective_at
 * @property Carbon|null $ended_at
 */
class CaseRiskAssessment extends Model
{
    /** @use HasFactory<CaseRiskAssessmentFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    /** @return BelongsTo<ServiceCase, $this> */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    protected function casts(): array
    {
        return [
            'classification' => CaseClassification::class,
            'encrypted_content' => ClassifiedValueCast::class.':case_risk_assessment',
            'effective_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
