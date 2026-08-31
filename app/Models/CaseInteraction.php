<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use Database\Factories\CaseInteractionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $service_case_id
 * @property string $interaction_type
 * @property ClassifiedValue $encrypted_content
 * @property Carbon $occurred_at
 * @property Carbon $recorded_at
 * @property-read ServiceCase $serviceCase
 */
class CaseInteraction extends Model
{
    /** @use HasFactory<CaseInteractionFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    public $timestamps = false;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Case interactions are append-only.'));
        static::deleting(fn () => throw new LogicException('Case interactions are append-only.'));
    }

    /** @return BelongsTo<ServiceCase, $this> */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    protected function casts(): array
    {
        return ['encrypted_content' => ClassifiedValueCast::class.':case_interaction', 'occurred_at' => 'datetime', 'recorded_at' => 'datetime'];
    }
}
