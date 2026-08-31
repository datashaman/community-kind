<?php

namespace App\Actions\Demo;

use App\Enums\PartyBusinessRole;
use App\Enums\PartyKind;
use App\Enums\SupporterRegistrationKind;
use App\Enums\SupporterRegistrationStatus;
use App\Enums\VolunteerApplicationStatus;
use App\Enums\VolunteerAssignmentStatus;
use App\Enums\VolunteerCredentialStatus;
use App\Enums\VolunteerOnboardingStatus;
use App\Enums\VolunteerOpportunityStatus;
use App\Enums\VolunteerShiftStatus;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyRole;
use App\Models\SupporterRegistration;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerCredential;
use App\Models\VolunteerHourEntry;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerShift;
use Carbon\CarbonImmutable;
use Ramsey\Uuid\Uuid;

final class BuildVolunteerScenario
{
    public function handle(Organisation $organisation, User $actor, string $reportingAt): void
    {
        $reportingAt = CarbonImmutable::parse($reportingAt);
        $opportunityId = Uuid::uuid5($organisation->uuid, 'volunteer-opportunity:community-garden')->toString();
        $opportunity = VolunteerOpportunity::query()->updateOrCreate(
            ['id' => $opportunityId],
            [
                'organisation_id' => $organisation->id,
                'title' => 'Community garden working day',
                'summary' => 'A fictional volunteer opportunity used to demonstrate applications, onboarding, shifts, and contribution reporting.',
                'interest_tags' => ['gardening', 'community'],
                'capacity' => 100,
                'status' => VolunteerOpportunityStatus::Published,
                'registration_opens_at' => $reportingAt->subMonths(2),
                'registration_closes_at' => $reportingAt->subMonth(),
                'starts_at' => $reportingAt->subDays(7),
                'ends_at' => $reportingAt->subDays(7)->addHours(4),
                'published_at' => $reportingAt->subMonths(2),
                'created_by_user_id' => $actor->id,
            ],
        );
        $shift = VolunteerShift::query()->updateOrCreate(
            ['id' => Uuid::uuid5($organisation->uuid, 'volunteer-shift:community-garden')->toString()],
            [
                'organisation_id' => $organisation->id,
                'volunteer_opportunity_id' => $opportunity->id,
                'title' => 'Morning garden shift',
                'starts_at' => $reportingAt->subDays(7),
                'ends_at' => $reportingAt->subDays(7)->addHours(4),
                'capacity' => 100,
                'status' => VolunteerShiftStatus::Completed,
                'created_by_user_id' => $actor->id,
            ],
        );

        Party::query()->where('kind', PartyKind::Person)->orderBy('id')->limit(100)->get()->each(function (Party $party, int $index) use ($actor, $opportunity, $organisation, $reportingAt, $shift): void {
            PartyRole::query()->firstOrCreate(['organisation_id' => $organisation->id, 'party_id' => $party->id, 'role' => PartyBusinessRole::Volunteer]);
            $registration = SupporterRegistration::query()->updateOrCreate(
                ['id' => Uuid::uuid5($organisation->uuid, "volunteer-registration:{$party->uuid}")->toString()],
                ['organisation_id' => $organisation->id, 'party_id' => $party->id, 'kind' => SupporterRegistrationKind::Volunteer, 'title' => $opportunity->title, 'status' => SupporterRegistrationStatus::Confirmed, 'version' => 2, 'starts_at' => $opportunity->starts_at],
            );
            $application = VolunteerApplication::query()->updateOrCreate(
                ['id' => Uuid::uuid5($organisation->uuid, "volunteer-application:{$party->uuid}")->toString()],
                ['organisation_id' => $organisation->id, 'volunteer_opportunity_id' => $opportunity->id, 'party_id' => $party->id, 'supporter_registration_id' => $registration->id, 'status' => VolunteerApplicationStatus::Approved, 'onboarding_status' => VolunteerOnboardingStatus::Complete, 'interests' => ['community'], 'availability' => ['weekend'], 'follow_up_status' => $index % 4 === 0 ? 'suppressed' : 'eligible', 'version' => 3, 'submitted_at' => $reportingAt->subDays(20), 'reviewed_at' => $reportingAt->subDays(15), 'reviewed_by_user_id' => $actor->id],
            );
            VolunteerCredential::query()->updateOrCreate(
                ['id' => Uuid::uuid5($organisation->uuid, "volunteer-credential:{$party->uuid}")->toString()],
                ['organisation_id' => $organisation->id, 'volunteer_application_id' => $application->id, 'party_id' => $party->id, 'type' => 'Safeguarding orientation', 'status' => VolunteerCredentialStatus::Verified, 'verified_at' => $reportingAt->subDays(30), 'expires_at' => $reportingAt->addYear(), 'recorded_by_user_id' => $actor->id],
            );
            $assignment = VolunteerAssignment::query()->updateOrCreate(
                ['id' => Uuid::uuid5($organisation->uuid, "volunteer-assignment:{$party->uuid}")->toString()],
                ['organisation_id' => $organisation->id, 'volunteer_shift_id' => $shift->id, 'volunteer_application_id' => $application->id, 'party_id' => $party->id, 'status' => VolunteerAssignmentStatus::Attended, 'version' => 2, 'confirmed_at' => $reportingAt->subDays(30), 'attended_at' => $shift->ends_at, 'transitioned_by_user_id' => $actor->id],
            );
            VolunteerHourEntry::query()->firstOrCreate(
                ['id' => Uuid::uuid5($organisation->uuid, "volunteer-hours:{$party->uuid}")->toString()],
                ['organisation_id' => $organisation->id, 'volunteer_assignment_id' => $assignment->id, 'party_id' => $party->id, 'minutes' => 240, 'occurred_at' => $shift->ends_at, 'recorded_by_user_id' => $actor->id],
            );
        });
    }
}
