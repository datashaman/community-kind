<?php

namespace App\Actions\Engagement;

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\Parties\RecordPartyTimelineEvent;
use App\Actions\Parties\StorePartyContact;
use App\Enums\PartnerProfileStatus;
use App\Enums\PartyBusinessRole;
use App\Enums\PartyContactType;
use App\Enums\PartyKind;
use App\Enums\PartyTimelineEventType;
use App\Enums\TenantAuditEventType;
use App\Models\Organisation;
use App\Models\PartnerProfile;
use App\Models\Party;
use App\Models\PartyRole;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;

final class CreatePartnerProfile
{
    public function __construct(private readonly OrganisationContext $context, private readonly StorePartyContact $storeContact, private readonly RecordPartyTimelineEvent $recordTimeline, private readonly RecordTenantAuditEvent $recordAudit) {}

    /** @param array{name: string, email: string|null, telephone: string|null, partner_type: string, relationship_summary: string} $attributes */
    public function handle(Organisation $organisation, array $attributes, User $actor): PartnerProfile
    {
        $this->context->ensureOwns($organisation->id);

        return DB::transaction(function () use ($actor, $attributes, $organisation): PartnerProfile {
            $party = Party::query()->create(['kind' => PartyKind::Organisation, 'display_name' => $attributes['name']]);
            if (filled($attributes['email'])) {
                $this->storeContact->handle($party, PartyContactType::Email, $attributes['email']);
            }
            if (filled($attributes['telephone'])) {
                $this->storeContact->handle($party, PartyContactType::Telephone, $attributes['telephone']);
            }
            PartyRole::query()->create(['organisation_id' => $organisation->id, 'party_id' => $party->id, 'role' => PartyBusinessRole::PartnerContact]);
            $profile = PartnerProfile::query()->create(['organisation_id' => $organisation->id, 'party_id' => $party->id, 'partner_type' => $attributes['partner_type'], 'status' => PartnerProfileStatus::Active, 'relationship_summary' => $attributes['relationship_summary'], 'engaged_at' => now(), 'created_by_user_id' => $actor->id]);
            $this->recordTimeline->handle($party, PartyTimelineEventType::PartnerEngagementRecorded, 'Partner profile created.', $actor, 'partner_profile', $profile->id, ['partner_type' => $profile->partner_type]);
            $this->recordAudit->handle($organisation, TenantAuditEventType::PartnerProfileCreated, 'partner_profile', $profile->id, ['partner_profile_id' => $profile->id, 'party_uuid' => $party->uuid], $actor);

            return $profile;
        });
    }
}
