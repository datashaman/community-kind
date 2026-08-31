<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use Database\Factories\PartyAddressFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $organisation_id
 * @property int $party_id
 * @property string $label
 * @property ClassifiedValue $encrypted_value
 * @property string|null $service_area
 * @property string $country_code
 */
#[Fillable(['organisation_id', 'party_id', 'label', 'encrypted_value', 'service_area', 'country_code'])]
class PartyAddress extends Model
{
    /** @use HasFactory<PartyAddressFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['encrypted_value' => ClassifiedValueCast::class.':party_address'];
    }
}
