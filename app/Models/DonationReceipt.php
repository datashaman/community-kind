<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\DonationReceiptFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $donation_id
 * @property string $donation_payment_id
 * @property string $receipt_number
 * @property int $amount_minor
 * @property string $currency
 * @property string $marker
 * @property Carbon $issued_at
 */
class DonationReceipt extends Model
{
    /** @use HasFactory<DonationReceiptFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    public $timestamps = false;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Donation receipts are append-only.'));
        static::deleting(fn () => throw new LogicException('Donation receipts are append-only.'));
    }

    /** @return BelongsTo<Donation, $this> */
    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    /** @return BelongsTo<DonationPayment, $this> */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(DonationPayment::class, 'donation_payment_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['issued_at' => 'datetime'];
    }
}
