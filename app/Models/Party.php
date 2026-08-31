<?php

namespace App\Models;

use App\Concerns\BelongsToOrganisation;
use App\Enums\PartyKind;
use App\OrganisationContext;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property int $organisation_id
 * @property PartyKind $kind
 * @property string $display_name
 * @property-read Organisation $organisation
 * @property-read Collection<int, Membership> $memberships
 * @property-read Collection<int, PartyContactPoint> $contactPoints
 * @property-read Collection<int, PortalAccessGrant> $portalAccessGrants
 * @property-read Collection<int, SupporterRegistration> $supporterRegistrations
 */
#[Fillable(['organisation_id', 'kind', 'display_name'])]
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use BelongsToOrganisation, HasFactory, HasUuids, SoftDeletes;

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /** @return BelongsTo<Organisation, $this> */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    /** @return HasMany<Membership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class, 'person_party_id');
    }

    /** @return HasMany<PartyContactPoint, $this> */
    public function contactPoints(): HasMany
    {
        return $this->hasMany(PartyContactPoint::class);
    }

    /** @return HasMany<PortalAccessGrant, $this> */
    public function portalAccessGrants(): HasMany
    {
        return $this->hasMany(PortalAccessGrant::class, 'person_party_id');
    }

    /** @return HasMany<SupporterRegistration, $this> */
    public function supporterRegistrations(): HasMany
    {
        return $this->hasMany(SupporterRegistration::class);
    }

    /** @return BelongsToMany<Program, $this> */
    public function programs(): BelongsToMany
    {
        return $this->belongsToMany(Program::class, 'party_program')
            ->withPivotValue('organisation_id', app(OrganisationContext::class)->id())
            ->withTimestamps();
    }

    /** @return HasMany<PartyRole, $this> */
    public function businessRoles(): HasMany
    {
        return $this->hasMany(PartyRole::class);
    }

    /** @return HasMany<PartyRelationship, $this> */
    public function relationships(): HasMany
    {
        return $this->hasMany(PartyRelationship::class);
    }

    /** @return HasMany<PartyAddress, $this> */
    public function addresses(): HasMany
    {
        return $this->hasMany(PartyAddress::class);
    }

    /** @return HasMany<PartyInterest, $this> */
    public function interests(): HasMany
    {
        return $this->hasMany(PartyInterest::class);
    }

    /** @return HasMany<PartyConsent, $this> */
    public function consents(): HasMany
    {
        return $this->hasMany(PartyConsent::class);
    }

    /** @return HasMany<PartySafeContactInstruction, $this> */
    public function safeContactInstructions(): HasMany
    {
        return $this->hasMany(PartySafeContactInstruction::class);
    }

    /** @return HasMany<PartyTimelineEvent, $this> */
    public function timelineEvents(): HasMany
    {
        return $this->hasMany(PartyTimelineEvent::class);
    }

    /** @return HasMany<ServiceCase, $this> */
    public function serviceCases(): HasMany
    {
        return $this->hasMany(ServiceCase::class);
    }

    /** @return HasMany<Donation, $this> */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /** @return HasMany<EventRegistration, $this> */
    public function eventRegistrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class);
    }

    /** @return HasMany<VolunteerApplication, $this> */
    public function volunteerApplications(): HasMany
    {
        return $this->hasMany(VolunteerApplication::class);
    }

    /** @return HasMany<VolunteerHourEntry, $this> */
    public function volunteerHourEntries(): HasMany
    {
        return $this->hasMany(VolunteerHourEntry::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kind' => PartyKind::class];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
