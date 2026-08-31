<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseAppointmentStatus;
use Database\Factories\CaseAppointmentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $service_case_id
 * @property ClassifiedValue $encrypted_content
 * @property CaseAppointmentStatus $status
 * @property int $version
 * @property Carbon $scheduled_at
 * @property Carbon|null $effective_at
 * @property string|null $terminal_reason
 * @property string|null $completed_service_id
 * @property-read ServiceCase $serviceCase
 */
class CaseAppointment extends Model
{
    /** @use HasFactory<CaseAppointmentFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    /** @return BelongsTo<ServiceCase, $this> */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    /** @return BelongsTo<CaseService, $this> */
    public function completedService(): BelongsTo
    {
        return $this->belongsTo(CaseService::class, 'completed_service_id');
    }

    protected function casts(): array
    {
        return ['encrypted_content' => ClassifiedValueCast::class.':case_appointment', 'status' => CaseAppointmentStatus::class, 'scheduled_at' => 'datetime', 'effective_at' => 'datetime'];
    }
}
