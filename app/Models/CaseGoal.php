<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseGoalStatus;
use Database\Factories\CaseGoalFactory;
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
 * @property CaseGoalStatus $status
 * @property int $version
 * @property Carbon|null $target_at
 * @property Carbon|null $effective_at
 * @property string|null $terminal_reason
 * @property-read ServiceCase $serviceCase
 */
class CaseGoal extends Model
{
    /** @use HasFactory<CaseGoalFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    /** @return BelongsTo<ServiceCase, $this> */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    protected function casts(): array
    {
        return ['encrypted_content' => ClassifiedValueCast::class.':case_goal', 'status' => CaseGoalStatus::class, 'target_at' => 'datetime', 'effective_at' => 'datetime'];
    }
}
