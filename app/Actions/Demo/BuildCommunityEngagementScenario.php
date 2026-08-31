<?php

namespace App\Actions\Demo;

use App\Enums\CommunityEventStatus;
use App\Enums\EventRegistrationStatus;
use App\Enums\InKindOfferStatus;
use App\Enums\PartnerCommitmentStatus;
use App\Enums\PartnerProfileStatus;
use App\Enums\PartyKind;
use App\Enums\SupporterRegistrationKind;
use App\Enums\SupporterRegistrationStatus;
use App\Models\CommunityEvent;
use App\Models\EventRegistration;
use App\Models\InKindOffer;
use App\Models\Organisation;
use App\Models\PartnerCommitment;
use App\Models\PartnerProfile;
use App\Models\Party;
use App\Models\SupporterRegistration;
use App\Models\User;
use Carbon\CarbonImmutable;
use Ramsey\Uuid\Uuid;

final class BuildCommunityEngagementScenario
{
    public function handle(Organisation $organisation, User $actor, string $reportingAt): void
    {
        $at = CarbonImmutable::parse($reportingAt);
        $attendee = Party::query()->where('kind', PartyKind::Person)->where('display_name', 'Synthetic Client Amina Example')->firstOrFail();
        $contributor = Party::query()->where('kind', PartyKind::Person)->where('display_name', 'Synthetic Donor Rowan Example')->firstOrFail();
        $partnerParty = Party::query()->where('kind', PartyKind::Organisation)->where('display_name', 'Synthetic Brightwell Community Fund')->firstOrFail();
        $event = CommunityEvent::query()->updateOrCreate(['id' => Uuid::uuid5($organisation->uuid, 'community-event:open-day')->toString()], ['organisation_id' => $organisation->id, 'title' => 'HarbourKind community open day', 'summary' => 'A fictional community gathering used to demonstrate the complete attendee journey.', 'capacity' => 50, 'status' => CommunityEventStatus::Completed, 'registration_opens_at' => $at->subMonths(2), 'registration_closes_at' => $at->subMonth(), 'starts_at' => $at->subDays(10), 'ends_at' => $at->subDays(10)->addHours(3), 'published_at' => $at->subMonths(2), 'created_by_user_id' => $actor->id]);
        $supporterRegistration = SupporterRegistration::query()->updateOrCreate(['id' => Uuid::uuid5($organisation->uuid, 'event-registration:amina')->toString()], ['organisation_id' => $organisation->id, 'party_id' => $attendee->id, 'kind' => SupporterRegistrationKind::Event, 'title' => $event->title, 'status' => SupporterRegistrationStatus::Confirmed, 'version' => 5, 'starts_at' => $event->starts_at]);
        EventRegistration::query()->updateOrCreate(['id' => Uuid::uuid5($organisation->uuid, 'event-attendance:amina')->toString()], ['organisation_id' => $organisation->id, 'community_event_id' => $event->id, 'party_id' => $attendee->id, 'supporter_registration_id' => $supporterRegistration->id, 'status' => EventRegistrationStatus::FollowedUp, 'version' => 4, 'registered_at' => $at->subDays(30), 'reminded_at' => $at->subDays(12), 'attended_at' => $event->starts_at, 'followed_up_at' => $at->subDays(8), 'transitioned_by_user_id' => $actor->id]);
        InKindOffer::query()->updateOrCreate(['id' => Uuid::uuid5($organisation->uuid, 'in-kind-offer:rowan')->toString()], ['organisation_id' => $organisation->id, 'party_id' => $contributor->id, 'category' => 'Food supplies', 'description' => 'Fictional shelf-stable pantry goods.', 'quantity' => 25, 'unit' => 'boxes', 'estimated_value_minor' => 50000, 'currency' => 'ZAR', 'condition' => 'new', 'status' => InKindOfferStatus::Fulfilled, 'fulfilment_outcome' => 'All 25 boxes received and distributed at the community open day.', 'version' => 3, 'offered_at' => $at->subDays(20), 'fulfilled_at' => $at->subDays(10), 'transitioned_by_user_id' => $actor->id]);
        $profile = PartnerProfile::query()->updateOrCreate(['id' => Uuid::uuid5($organisation->uuid, 'partner-profile:brightwell')->toString()], ['organisation_id' => $organisation->id, 'party_id' => $partnerParty->id, 'partner_type' => 'local_business', 'status' => PartnerProfileStatus::Active, 'relationship_summary' => 'Provides venue support and introduces local business volunteers.', 'engaged_at' => $at->subMonths(3), 'created_by_user_id' => $actor->id]);
        $commitment = PartnerCommitment::query()->updateOrCreate(['id' => Uuid::uuid5($organisation->uuid, 'partner-commitment:brightwell-open-day')->toString()], ['organisation_id' => $organisation->id, 'partner_profile_id' => $profile->id, 'title' => 'Host community open day', 'details' => 'Provide an accessible venue and two volunteer coordinators.', 'status' => PartnerCommitmentStatus::Completed, 'due_on' => $event->starts_at->toDateString(), 'completed_at' => $event->ends_at, 'recorded_by_user_id' => $actor->id]);
        $commitment->forceFill(['created_at' => $at->subDays(25)])->save();
    }
}
