<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\CaseWorkflowSubject;
use Database\Factories\WorkflowCorrectionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $service_case_id
 * @property CaseWorkflowSubject $subject_type
 * @property string $subject_id
 * @property string $correction_type
 * @property string $reason
 * @property array<string, bool|int|float|string|null>|null $replacement_values
 * @property Carbon $effective_at
 * @property Carbon $recorded_at
 * @property-read ServiceCase $serviceCase
 */
class WorkflowCorrection extends Model
{
    /** @use HasFactory<WorkflowCorrectionFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Workflow corrections are append-only.'));
        static::deleting(fn () => throw new LogicException('Workflow corrections are append-only.'));
    }

    /** @return BelongsTo<ServiceCase, $this> */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    protected function casts(): array
    {
        return ['subject_type' => CaseWorkflowSubject::class, 'replacement_values' => 'array', 'effective_at' => 'datetime', 'recorded_at' => 'datetime'];
    }
}
