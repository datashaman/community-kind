<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\RecurringMandateStatus;
use Database\Factories\RecurringMandateEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $recurring_mandate_id
 * @property string|null $donation_payment_id
 * @property string $idempotency_key
 * @property string $provider_event_id
 * @property RecurringMandateStatus $from_status
 * @property RecurringMandateStatus $to_status
 * @property Carbon $occurred_at
 */
class RecurringMandateEvent extends Model
{
    /** @use HasFactory<RecurringMandateEventFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Recurring Mandate events are append-only.'));
        static::deleting(fn () => throw new LogicException('Recurring Mandate events are append-only.'));
    }

    /** @return BelongsTo<RecurringMandate, $this> */
    public function mandate(): BelongsTo
    {
        return $this->belongsTo(RecurringMandate::class, 'recurring_mandate_id');
    }

    /** @return BelongsTo<DonationPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(DonationPayment::class, 'donation_payment_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_status' => RecurringMandateStatus::class, 'to_status' => RecurringMandateStatus::class, 'occurred_at' => 'datetime'];
    }
}
