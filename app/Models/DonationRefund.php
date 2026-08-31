<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\DonationRefundFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $donation_payment_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $idempotency_key
 * @property string $provider_refund_id
 * @property Carbon $occurred_at
 * @property-read DonationPayment $payment
 */
class DonationRefund extends Model
{
    /** @use HasFactory<DonationRefundFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    public $timestamps = false;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Donation refunds are append-only.'));
        static::deleting(fn () => throw new LogicException('Donation refunds are append-only.'));
    }

    /** @return BelongsTo<DonationPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(DonationPayment::class, 'donation_payment_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
