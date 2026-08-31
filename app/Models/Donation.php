<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\DonationFrequency;
use Database\Factories\DonationFactory;
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
 * @property int $party_id
 * @property int|null $fundraising_campaign_id
 * @property int $donation_fund_id
 * @property DonationFrequency $frequency
 * @property int $amount_minor
 * @property string $currency
 * @property string $source_code
 * @property string $idempotency_key
 * @property bool $is_simulated
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organisation $organisation
 * @property-read Party $party
 * @property-read FundraisingCampaign|null $campaign
 * @property-read DonationFund $fund
 * @property-read RecurringMandate|null $mandate
 * @property-read int $payments_count
 */
class Donation extends Model
{
    /** @use HasFactory<DonationFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    protected static function booted(): void
    {
        static::updating(function (Donation $donation): void {
            if ($donation->isDirty(['organisation_id', 'party_id', 'amount_minor', 'currency', 'frequency'])) {
                throw new LogicException('Donation identity and money fields are immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Donation history is immutable.'));
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<FundraisingCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FundraisingCampaign::class, 'fundraising_campaign_id');
    }

    /** @return BelongsTo<DonationFund, $this> */
    public function fund(): BelongsTo
    {
        return $this->belongsTo(DonationFund::class, 'donation_fund_id');
    }

    /** @return HasOne<RecurringMandate, $this> */
    public function mandate(): HasOne
    {
        return $this->hasOne(RecurringMandate::class);
    }

    /** @return HasMany<DonationPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(DonationPayment::class);
    }

    /** @return HasMany<DonationReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(DonationReceipt::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['frequency' => DonationFrequency::class, 'is_simulated' => 'boolean'];
    }
}
