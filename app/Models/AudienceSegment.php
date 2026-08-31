<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\AudienceSegmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $name
 * @property array{purpose: string, channel: string, role: string, service_area: string|null, interest: string|null, donation_activity: bool, campaign_source: string|null, activity_type?: string, recency_days?: int|null, minimum_frequency?: int, minimum_value?: int|null} $criteria
 */
#[Fillable(['organisation_id', 'name', 'criteria', 'created_by_user_id'])]
class AudienceSegment extends Model
{
    /** @use HasFactory<AudienceSegmentFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (AudienceSegment $segment): void {
            if ($segment->isDirty(['organisation_id', 'criteria'])) {
                throw new LogicException('Saved audience criteria are immutable; create a new definition instead.');
            }
        });
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['criteria' => 'array'];
    }
}
