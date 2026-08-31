<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\CaseMetricCode;
use Database\Factories\MetricEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property int $program_id
 * @property CaseMetricCode $code
 * @property string $value
 * @property array<string, bool|int|float|string|null> $dimensions
 * @property string $deduplication_key
 * @property Carbon $occurred_at
 * @property Carbon $recorded_at
 */
class MetricEvent extends Model
{
    /** @use HasFactory<MetricEventFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Metric events are append-only.'));
        static::deleting(fn () => throw new LogicException('Metric events are append-only.'));
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    protected function casts(): array
    {
        return ['code' => CaseMetricCode::class, 'dimensions' => 'array', 'value' => 'decimal:4', 'occurred_at' => 'datetime', 'recorded_at' => 'datetime'];
    }
}
