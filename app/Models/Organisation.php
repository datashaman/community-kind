<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueOrganisationSlugs;
use App\Enums\OrganisationStatus;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property OrganisationStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, OrganisationInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 */
#[Fillable(['name', 'slug', 'status'])]
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use GeneratesUniqueOrganisationSlugs, HasFactory, SoftDeletes;

    /** @var array<string, mixed> */
    protected $attributes = [
        'status' => OrganisationStatus::Pending->value,
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Organisation $organisation) {
            if (empty($organisation->slug)) {
                $organisation->slug = static::generateUniqueOrganisationSlug($organisation->name);
            }
        });

        static::updating(function (Organisation $organisation) {
            if ($organisation->isDirty('name')) {
                $organisation->slug = static::generateUniqueOrganisationSlug($organisation->name, $organisation->id);
            }
        });
    }

    /**
     * Get the organisation owner.
     */
    public function owner(): ?Model
    {
        return $this->members()
            ->wherePivot('is_owner', true)
            ->first();
    }

    /**
     * Get all members of this organisation.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organisation_members', 'organisation_id', 'user_id')
            ->using(Membership::class)
            ->withPivot(['role', 'is_owner'])
            ->withTimestamps();
    }

    /**
     * Get all memberships for this organisation.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * Get all invitations for this organisation.
     *
     * @return HasMany<OrganisationInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(OrganisationInvitation::class);
    }

    /**
     * Get the programs configured for this Organisation.
     *
     * @return HasMany<Program, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganisationStatus::class,
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
