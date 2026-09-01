<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\OrganisationContext;
use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $name
 * @property string $slug
 * @property string $request_label
 * @property string $case_label
 * @property array<string, mixed> $configuration
 * @property-read Organisation $organisation
 * @property-read Collection<int, ProgramStage> $stages
 * @property-read Collection<int, ProgramOutcomeMeasure> $outcomeMeasures
 * @property-read Collection<int, ProgramTaxonomy> $taxonomies
 */
#[Fillable(['organisation_id', 'name', 'slug', 'request_label', 'case_label', 'configuration'])]
class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use BelongsToOrganisation, HasFactory, Searchable, SoftDeletes;

    /**
     * Get the Organisation that owns the program.
     *
     * @return BelongsTo<Organisation, $this>
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /**
     * Get the memberships with access to the program.
     *
     * @return BelongsToMany<Membership, $this>
     */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Membership::class, 'membership_program', 'program_id', 'membership_id')
            ->withPivotValue('organisation_id', app(OrganisationContext::class)->id());
    }

    /** @return BelongsToMany<Party, $this> */
    public function parties(): BelongsToMany
    {
        return $this->belongsToMany(Party::class, 'party_program')
            ->withPivotValue('organisation_id', app(OrganisationContext::class)->id())
            ->withTimestamps();
    }

    /** @return HasMany<ProgramStage, $this> */
    public function stages(): HasMany
    {
        return $this->hasMany(ProgramStage::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<ProgramOutcomeMeasure, $this> */
    public function outcomeMeasures(): HasMany
    {
        return $this->hasMany(ProgramOutcomeMeasure::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<ProgramTaxonomy, $this> */
    public function taxonomies(): HasMany
    {
        return $this->hasMany(ProgramTaxonomy::class)->orderBy('position')->orderBy('id');
    }

    /** @return array<string, int|string> */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'organisation_id' => $this->organisation_id,
            'name' => $this->name,
            'slug' => $this->slug,
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['configuration' => 'array'];
    }
}
