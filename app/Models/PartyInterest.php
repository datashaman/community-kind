<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\PartyInterestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organisation_id', 'party_id', 'slug', 'label'])]
class PartyInterest extends Model
{
    /** @use HasFactory<PartyInterestFactory> */
    use BelongsToOrganisation, HasFactory;

    /** @return BelongsTo<Party, $this> */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
