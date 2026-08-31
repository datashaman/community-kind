<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseServiceStatus;
use Database\Factories\CaseServiceFactory;
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
 * @property string $service_code
 * @property CaseServiceStatus $status
 * @property int $version
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $delivered_at
 * @property string|null $terminal_reason
 * @property-read ServiceCase $serviceCase
 */
class CaseService extends Model
{
    /** @use HasFactory<CaseServiceFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    /** @return BelongsTo<ServiceCase, $this> */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    protected function casts(): array
    {
        return ['encrypted_content' => ClassifiedValueCast::class.':case_service', 'status' => CaseServiceStatus::class, 'scheduled_for' => 'datetime', 'delivered_at' => 'datetime'];
    }
}
