<?php

namespace App\Models;

use App\Concerns\GeneratesUniqueOrganisationSlugs;
use App\Enums\OrganisationStatus;
use App\OrganisationContext;
use Database\Factories\OrganisationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $slug
 * @property OrganisationStatus $status
 * @property Carbon|null $status_changed_at
 * @property Carbon|null $deletion_scheduled_for
 * @property Carbon|null $signed_links_invalidated_at
 * @property int $access_version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property string|null $sandbox_pair_id
 * @property string|null $sandbox_template
 * @property int $demo_generation
 * @property bool $is_synthetic
 * @property-read SandboxPair|null $sandboxPair
 * @property-read Collection<int, OrganisationInvitation> $invitations
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, PortalAccessGrant> $portalAccessGrants
 * @property-read Collection<int, SupporterRegistration> $supporterRegistrations
 */
#[Fillable(['name', 'slug', 'status', 'status_changed_at', 'deletion_scheduled_for', 'signed_links_invalidated_at', 'access_version', 'sandbox_pair_id', 'sandbox_template', 'demo_generation', 'is_synthetic'])]
class Organisation extends Model
{
    /** @use HasFactory<OrganisationFactory> */
    use GeneratesUniqueOrganisationSlugs, HasFactory, HasUuids, SoftDeletes;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

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

            $organisation->status_changed_at ??= Carbon::now();
        });
    }

    /**
     * Get the organisation owner.
     */
    public function owner(): ?User
    {
        return $this->members()
            ->wherePivot('is_owner', true)
            ->first();
    }

    public function hasOtherCapableOwner(Membership $membership): bool
    {
        return app(OrganisationContext::class)->run($this, fn (): bool => $this->memberships()
            ->where('is_owner', true)
            ->whereKeyNot($membership->id)
            ->get()
            ->contains(fn (Membership $ownerMembership) => ! $ownerMembership->isHeld()));
    }

    /**
     * Get the organisation's owners.
     *
     * @return BelongsToMany<User, $this, Membership, 'pivot'>
     */
    public function owners(): BelongsToMany
    {
        return $this->members()->wherePivot('is_owner', true);
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
            ->withPivot(['id', 'person_party_id', 'role', 'is_owner', 'accepted_at', 'ended_at'])
            ->wherePivotNull('ended_at')
            ->withTimestamps();
    }

    /**
     * Get all memberships for this organisation.
     *
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class)->whereNull('ended_at');
    }

    /** @return HasMany<Membership, $this> */
    public function membershipHistory(): HasMany
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

    /** @return BelongsTo<SandboxPair, $this> */
    public function sandboxPair(): BelongsTo
    {
        return $this->belongsTo(SandboxPair::class);
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

    /** @return HasMany<Party, $this> */
    public function parties(): HasMany
    {
        return $this->hasMany(Party::class);
    }

    /** @return HasMany<PartyContactPoint, $this> */
    public function partyContactPoints(): HasMany
    {
        return $this->hasMany(PartyContactPoint::class);
    }

    /** @return HasMany<OrganisationAccessHold, $this> */
    public function accessHolds(): HasMany
    {
        return $this->hasMany(OrganisationAccessHold::class);
    }

    /** @return HasMany<OrganisationLifecycleEvent, $this> */
    public function lifecycleEvents(): HasMany
    {
        return $this->hasMany(OrganisationLifecycleEvent::class);
    }

    /** @return HasMany<TenantAuditEvent, $this> */
    public function auditEvents(): HasMany
    {
        return $this->hasMany(TenantAuditEvent::class);
    }

    /** @return HasMany<PortalAccessGrant, $this> */
    public function portalAccessGrants(): HasMany
    {
        return $this->hasMany(PortalAccessGrant::class);
    }

    /** @return HasMany<SupporterRegistration, $this> */
    public function supporterRegistrations(): HasMany
    {
        return $this->hasMany(SupporterRegistration::class);
    }

    /** @return HasMany<OrganisationOwnershipTransfer, $this> */
    public function ownershipTransfers(): HasMany
    {
        return $this->hasMany(OrganisationOwnershipTransfer::class);
    }

    /** @return HasMany<OrganisationSlug, $this> */
    public function previousSlugs(): HasMany
    {
        return $this->hasMany(OrganisationSlug::class);
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
            'status_changed_at' => 'datetime',
            'deletion_scheduled_for' => 'datetime',
            'signed_links_invalidated_at' => 'datetime',
            'access_version' => 'integer',
            'demo_generation' => 'integer',
            'is_synthetic' => 'boolean',
        ];
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function resolveRouteBinding(mixed $value, mixed $field = null): ?Model
    {
        $organisation = parent::resolveRouteBinding($value, $field);

        if ($organisation !== null || ! is_string($value) || ($field !== null && $field !== 'slug')) {
            return $organisation;
        }

        $previousSlug = OrganisationSlug::query()
            ->with('organisation')
            ->whereHas('organisation')
            ->where('slug', $value)
            ->where('redirect_until', '>', now())
            ->first();

        if ($previousSlug === null) {
            return null;
        }

        $segments = request()->segments();
        $segmentIndex = array_search($value, $segments, true);

        if ($segmentIndex === false) {
            return null;
        }

        $segments[$segmentIndex] = $previousSlug->organisation->slug;
        $url = url(implode('/', $segments));

        if (request()->getQueryString() !== null) {
            $url .= '?'.request()->getQueryString();
        }

        throw new HttpResponseException(redirect($url, request()->isMethodSafe() ? 302 : 307));
    }
}
