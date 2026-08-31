<?php

namespace App\Models;

use App\Casts\ClassifiedValueCast;
use App\Concerns\BelongsToOrganisation;
use App\Data\Values\ClassifiedValue;
use Database\Factories\PartySafeContactInstructionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property ClassifiedValue $encrypted_value
 * @property string $source
 * @property Carbon $effective_at
 * @property Carbon|null $ended_at
 */
#[Fillable(['organisation_id', 'party_id', 'encrypted_value', 'source', 'effective_at', 'ended_at', 'recorded_by_user_id'])]
class PartySafeContactInstruction extends Model
{
    /** @use HasFactory<PartySafeContactInstructionFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids;

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'encrypted_value' => ClassifiedValueCast::class.':party_safe_contact',
            'effective_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
