<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use App\Enums\ExternalReferralStatus;
use Database\Factories\ExternalReferralFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $organisation_id
 * @property string $service_case_id
 * @property ClassifiedValue $encrypted_content
 * @property ExternalReferralStatus $status
 * @property int $version
 * @property string $sharing_authority
 * @property Carbon|null $sent_at
 * @property Carbon|null $effective_at
 * @property Carbon|null $carried_forward_at
 * @property string|null $terminal_reason
 * @property string|null $carry_forward_reason
 * @property-read ServiceCase $serviceCase
 */
class ExternalReferral extends Model
{
    /** @use HasFactory<ExternalReferralFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $guarded = ['organisation_id'];

    /** @return BelongsTo<ServiceCase, $this> */
    public function serviceCase(): BelongsTo
    {
        return $this->belongsTo(ServiceCase::class);
    }

    protected function casts(): array
    {
        return ['encrypted_content' => ClassifiedValueCast::class.':external_referral', 'status' => ExternalReferralStatus::class, 'sent_at' => 'datetime', 'effective_at' => 'datetime', 'carried_forward_at' => 'datetime'];
    }
}
