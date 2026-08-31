<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\DonationFundFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $name
 * @property string $slug
 * @property bool $is_simulated
 */
#[Fillable(['organisation_id', 'name', 'slug', 'is_simulated'])]
class DonationFund extends Model
{
    /** @use HasFactory<DonationFundFactory> */
    use BelongsToOrganisation, HasFactory;

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<Donation, $this> */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_simulated' => 'boolean'];
    }
}
