<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\ProgramTaxonomyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $program_id
 * @property string $key
 * @property string $label
 * @property int $position
 * @property Carbon|null $retired_at
 * @property-read Collection<int, ProgramTaxonomyValue> $values
 */
#[Fillable(['organisation_id', 'program_id', 'key', 'label', 'position', 'retired_at'])]
class ProgramTaxonomy extends Model
{
    /** @use HasFactory<ProgramTaxonomyFactory> */
    use BelongsToOrganisation, HasFactory;

    protected static function booted(): void
    {
        static::updating(function (ProgramTaxonomy $taxonomy): void {
            if ($taxonomy->isDirty(['program_id', 'key'])) {
                throw new LogicException('A Program Taxonomy stable identity is immutable.');
            }
        });
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return HasMany<ProgramTaxonomyValue, $this> */
    public function values(): HasMany
    {
        return $this->hasMany(ProgramTaxonomyValue::class)->orderBy('position')->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['retired_at' => 'immutable_datetime'];
    }
}
