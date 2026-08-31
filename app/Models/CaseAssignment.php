<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\CaseAssignmentRole;
use App\Enums\CaseAssignmentStatus;
use Database\Factories\CaseAssignmentFactory;
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
 * @property int $membership_id
 * @property CaseAssignmentRole $role
 * @property CaseAssignmentStatus $status
 * @property bool|null $active_primary_marker
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property string|null $assigned_reason
 * @property string|null $ended_reason
 * @property-read Membership $membership
 * @property-read ServiceCase $serviceCase
 */
class CaseAssignment extends Model
{
    /** @use HasFactory<CaseAssignmentFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(function (CaseAssignment $assignment): void {
            $allowedChanges = ['status', 'active_primary_marker', 'ended_at', 'ended_reason', 'ended_by_user_id'];

            if ($assignment->getRawOriginal('status') !== CaseAssignmentStatus::Active->value
                || $assignment->status !== CaseAssignmentStatus::Ended
                || array_diff(array_keys($assignment->getDirty()), $allowedChanges) !== []) {
                throw new LogicException('Case assignment history may only be ended.');
            }
        });
        static::deleting(fn () => throw new LogicException('Case assignment history is append-only.'));
    }

    /** @return BelongsTo<ServiceCase, $this> */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    /** @return BelongsTo<Membership, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(Membership::class);
    }

    protected function casts(): array
    {
        return [
            'role' => CaseAssignmentRole::class,
            'status' => CaseAssignmentStatus::class,
            'active_primary_marker' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
