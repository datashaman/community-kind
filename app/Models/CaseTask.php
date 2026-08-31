<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseTaskStatus;
use Database\Factories\CaseTaskFactory;
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
 * @property CaseTaskStatus $status
 * @property int $version
 * @property Carbon|null $due_at
 * @property Carbon|null $effective_at
 * @property string|null $terminal_reason
 * @property-read ServiceCase $serviceCase
 */
class CaseTask extends Model
{
    /** @use HasFactory<CaseTaskFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    /** @return BelongsTo<ServiceCase, $this> */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    protected function casts(): array
    {
        return ['encrypted_content' => ClassifiedValueCast::class.':case_task', 'status' => CaseTaskStatus::class, 'due_at' => 'datetime', 'effective_at' => 'datetime'];
    }
}
