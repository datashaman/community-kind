<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\DonationPaymentStatus;
use Database\Factories\DonationPaymentEventFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $donation_payment_id
 * @property string $idempotency_key
 * @property string $provider_event_id
 * @property DonationPaymentStatus $from_status
 * @property DonationPaymentStatus $to_status
 * @property Carbon $occurred_at
 */
class DonationPaymentEvent extends Model
{
    /** @use HasFactory<DonationPaymentEventFactory> */
    use BelongsToOrganisation, HasFactory, HasUlids;

    public $timestamps = false;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Donation Payment events are append-only.'));
        static::deleting(fn () => throw new LogicException('Donation Payment events are append-only.'));
    }

    /** @return BelongsTo<DonationPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(DonationPayment::class, 'donation_payment_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_status' => DonationPaymentStatus::class, 'to_status' => DonationPaymentStatus::class, 'occurred_at' => 'datetime'];
    }
}
