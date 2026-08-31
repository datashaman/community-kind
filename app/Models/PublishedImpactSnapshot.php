<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Carbon\CarbonImmutable;
use Database\Factories\PublishedImpactSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $audience
 * @property string $registry_version
 * @property list<array<string, mixed>> $metrics
 * @property list<array<string, mixed>> $cohort_comparisons
 * @property array<string, string> $period
 * @property array<string, mixed> $filters
 * @property CarbonImmutable $approved_at
 * @property CarbonImmutable|null $published_at
 * @property-read Organisation $organisation
 */
#[Fillable(['organisation_id', 'audience', 'registry_version', 'metrics', 'cohort_comparisons', 'period', 'filters', 'approved_at', 'approved_by_user_id', 'published_at'])]
class PublishedImpactSnapshot extends Model
{
    /** @use HasFactory<PublishedImpactSnapshotFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Published impact snapshots are immutable.'));
        static::deleting(fn () => throw new LogicException('Published impact snapshots are immutable.'));
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    protected function casts(): array
    {
        return [
            'metrics' => 'array',
            'cohort_comparisons' => 'array',
            'period' => 'array',
            'filters' => 'array',
            'approved_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
        ];
    }
}
