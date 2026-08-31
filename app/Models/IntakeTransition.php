<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\IntakeStatus;
use Database\Factories\IntakeTransitionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $intake_request_id
 * @property IntakeStatus|null $from_status
 * @property IntakeStatus $to_status
 * @property string|null $reason
 * @property Carbon $effective_at
 * @property Carbon $recorded_at
 * @property int $version
 */
class IntakeTransition extends Model
{
    /** @use HasFactory<IntakeTransitionFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Intake transitions are append-only.'));
        static::deleting(fn () => throw new LogicException('Intake transitions are append-only.'));
    }

    /** @return BelongsTo<IntakeRequest, $this> */
    public function intakeRequest(): BelongsTo
    {
        return $this->belongsTo(IntakeRequest::class);
    }

    protected function casts(): array
    {
        return [
            'from_status' => IntakeStatus::class,
            'to_status' => IntakeStatus::class,
            'effective_at' => 'datetime',
            'recorded_at' => 'datetime',
        ];
    }
}
