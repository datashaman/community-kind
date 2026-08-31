<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use App\Enums\PartyContactType;
use Database\Factories\PartyContactPointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $organisation_id
 * @property int $party_id
 * @property PartyContactType $type
 * @property ClassifiedValue $encrypted_value
 * @property string $data_key_version
 * @property string $current_index_key_version
 * @property string $current_blind_index
 * @property string|null $previous_index_key_version
 * @property string|null $previous_blind_index
 * @property-read Organisation $organisation
 * @property-read Party $party
 */
#[Fillable(['party_id', 'type', 'encrypted_value'])]
class PartyContactPoint extends Model
{
    /** @use HasFactory<PartyContactPointFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    protected $hidden = [
        'encrypted_value',
        'current_blind_index',
        'previous_blind_index',
    ];

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class)->withTrashed();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => PartyContactType::class,
            'encrypted_value' => ClassifiedValueCast::class.':party_contact',
        ];
    }
}
