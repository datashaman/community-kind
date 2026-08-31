<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\RecurringMandateStatus;
use Database\Factories\RecurringMandateFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $donation_id
 * @property int $party_id
 * @property int $amount_minor
 * @property string $currency
 * @property string $interval
 * @property RecurringMandateStatus $status
 * @property int $version
 * @property string $provider_mandate_id
 * @property Carbon|null $cancelled_at
 * @property-read Donation $donation
 * @property-read Party $party
 */
class RecurringMandate extends Model
{
    /** @use HasFactory<RecurringMandateFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(function (RecurringMandate $mandate): void {
            if ($mandate->isDirty(['organisation_id', 'donation_id', 'party_id', 'amount_minor', 'currency', 'interval', 'provider_mandate_id'])) {
                throw new LogicException('Recurring Mandate identity and money fields are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Recurring Mandate history is immutable.'));
    }

    /** @return BelongsTo<Donation, $this> */
    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return HasMany<DonationPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(DonationPayment::class);
    }

    /** @return HasMany<RecurringMandateEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(RecurringMandateEvent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['status' => RecurringMandateStatus::class, 'cancelled_at' => 'datetime'];
    }
}
