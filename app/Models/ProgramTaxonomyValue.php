<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use Database\Factories\ProgramTaxonomyValueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $organisation_id
 * @property int $program_taxonomy_id
 * @property string $key
 * @property string $label
 * @property int $position
 * @property Carbon|null $retired_at
 */
#[Fillable(['organisation_id', 'program_taxonomy_id', 'key', 'label', 'position', 'retired_at'])]
class ProgramTaxonomyValue extends Model
{
    /** @use HasFactory<ProgramTaxonomyValueFactory> */
    use BelongsToOrganisation, HasFactory;

    protected static function booted(): void
    {
        static::updating(function (ProgramTaxonomyValue $value): void {
            if ($value->isDirty(['program_taxonomy_id', 'key'])) {
                throw new LogicException('A Program Taxonomy Value stable identity is immutable.');
            }
        });
    }

    /** @return BelongsTo<ProgramTaxonomy, $this> */
    public function taxonomy(): BelongsTo
    {
        return $this->belongsTo(ProgramTaxonomy::class, 'program_taxonomy_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['retired_at' => 'immutable_datetime'];
    }
}
