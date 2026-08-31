<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\DonationPaymentStatus;
use Database\Factories\DonationPaymentFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $donation_id
 * @property string|null $recurring_mandate_id
 * @property int $attempt_number
 * @property int $amount_minor
 * @property string $currency
 * @property DonationPaymentStatus $status
 * @property int $version
 * @property string $idempotency_key
 * @property string $provider_payment_id
 * @property Carbon|null $settled_at
 * @property-read Donation $donation
 * @property-read RecurringMandate|null $mandate
 * @property-read DonationReceipt|null $receipt
 */
class DonationPayment extends Model
{
    /** @use HasFactory<DonationPaymentFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(function (DonationPayment $payment): void {
            if ($payment->isDirty(['organisation_id', 'donation_id', 'recurring_mandate_id', 'attempt_number', 'provider_payment_id'])) {
                throw new LogicException('Donation Payment identity fields are immutable.');
            }

            if ($payment->isDirty(['amount_minor', 'currency'])
                && ($payment->getRawOriginal('status') !== DonationPaymentStatus::Created->value || $payment->status !== DonationPaymentStatus::Created)) {
                throw new LogicException('Donation Payment money fields are immutable after collection begins.');
            }
        });
        static::deleting(fn () => throw new LogicException('Donation Payment history is immutable.'));
    }

    /** @return BelongsTo<Donation, $this> */
    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    /** @return BelongsTo<RecurringMandate, $this> */
    public function mandate(): BelongsTo
    {
        return $this->belongsTo(RecurringMandate::class, 'recurring_mandate_id');
    }

    /** @return HasMany<DonationPaymentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(DonationPaymentEvent::class);
    }

    /** @return HasMany<DonationRefund, $this> */
    public function refunds(): HasMany
    {
        return $this->hasMany(DonationRefund::class);
    }

    /** @return HasOne<DonationReceipt, $this> */
    public function receipt(): HasOne
    {
        return $this->hasOne(DonationReceipt::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => DonationPaymentStatus::class, 'settled_at' => 'datetime'];
    }
}
