<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\SupporterRegistrationKind;
use App\Enums\SupporterRegistrationStatus;
use Database\Factories\SupporterRegistrationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property string $id
 * @property int $organisation_id
 * @property int $party_id
 * @property SupporterRegistrationKind $kind
 * @property string $title
 * @property SupporterRegistrationStatus $status
 * @property int $version
 * @property Carbon|null $starts_at
 * @property Carbon|null $cancelled_at
 * @property-read Party $party
 */
#[Fillable(['organisation_id', 'party_id', 'kind', 'title', 'status', 'version', 'starts_at', 'cancelled_at'])]
class SupporterRegistration extends Model
{
    /** @use HasFactory<SupporterRegistrationFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected static function booted(): void
    {
        static::updating(function (SupporterRegistration $registration): void {
            if ($registration->isDirty(['organisation_id', 'party_id', 'kind', 'title', 'starts_at'])) {
                throw new LogicException('Supporter Registration identity is immutable.');
            }
        });
        static::deleting(fn () => throw new LogicException('Supporter Registration history is immutable.'));
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'kind' => SupporterRegistrationKind::class,
            'status' => SupporterRegistrationStatus::class,
            'version' => 'integer',
            'starts_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
