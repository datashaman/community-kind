<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\OrganisationContext;
use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

/**
 * @property int $id
 * @property int $organisation_id
 * @property string $name
 * @property string $slug
 * @property array<string, mixed> $configuration
 * @property-read Organisation $organisation
 */
#[Fillable(['organisation_id', 'name', 'slug', 'configuration'])]
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
