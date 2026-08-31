<?php

use App\Actions\Engagement\EvaluateAudienceSegment;
use App\Actions\Parties\RecordPartyConsent;
use App\Actions\Parties\SyncPartyContacts;
use App\Actions\Portal\IssuePortalAccessGrant;
use App\Enums\ConsentChannel;
use App\Enums\ConsentDecision;
use App\Enums\ConsentPurpose;
use App\Enums\OrganisationRole;
use App\Enums\PartyBusinessRole;
use App\Enums\RecurringMandateStatus;
use App\Enums\SupporterRegistrationKind;
use App\Enums\SupporterRegistrationStatus;
use App\Enums\TenantAuditEventType;
use App\Enums\VolunteerApplicationStatus;
use App\Enums\VolunteerAssignmentStatus;
use App\Models\AudienceSegment;
use App\Models\Donation;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyRole;
use App\Models\PortalAccessGrant;
use App\Models\RecurringMandate;
use App\Models\SupporterRegistration;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\Models\VolunteerApplication;
use App\Models\VolunteerAssignment;
use App\Models\VolunteerCredential;
use App\Models\VolunteerOpportunity;
use App\Models\VolunteerShift;
use App\OrganisationContext;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{organisation: Organisation, otherOrganisation: Organisation, party: Party, otherParty: Party, staff: User, supporter: User, grant: PortalAccessGrant, token: string} */
function supporterPortalFixture(): array
{
    $organisation = Organisation::factory()->active()->create(['slug' => 'supporter-home']);
    $otherOrganisation = Organisation::factory()->active()->create(['slug' => 'other-home']);
    $staff = User::factory()->create();
    $supporter = User::factory()->create(['email' => 'supporter@example.test']);
    $organisation->memberships()->create(['user_id' => $staff->id, 'role' => OrganisationRole::EngagementOfficer]);

    [$party, $otherParty] = app(OrganisationContext::class)->run($organisation, function () use ($organisation): array {
        $party = Party::factory()->for($organisation)->create(['display_name' => 'Amina Supporter']);
        $otherParty = Party::factory()->for($organisation)->create(['display_name' => 'Private Other Person']);
        PartyRole::factory()->create(['party_id' => $party->id, 'role' => PartyBusinessRole::Donor]);
        PartyRole::factory()->create(['party_id' => $otherParty->id, 'role' => PartyBusinessRole::Client]);
        app(SyncPartyContacts::class)->handle($party, ['email' => 'amina@example.test', 'telephone' => '+27 82 555 0100']);

        return [$party, $otherParty];
    });

    $issued = app(OrganisationContext::class)->run(
        $organisation,
        fn (): array => app(IssuePortalAccessGrant::class)->handle($party, $supporter, $staff),
    );

    return [
        'organisation' => $organisation,
        'otherOrganisation' => $otherOrganisation,
        'party' => $party,
        'otherParty' => $otherParty,
        'staff' => $staff,
        'supporter' => $supporter,
        'grant' => $issued['grant'],
        'token' => $issued['token'],
    ];
}

function supporterPortalUrl(array $fixture, string $path = '/portal'): string
{
    return "https://{$fixture['organisation']->slug}.community-kind.test{$path}";
}

it('issues a hashed single-use grant only to a verified user and replaces an earlier grant', function () {
    $fixture = supporterPortalFixture();

    expect($fixture['grant']->token_hash)->toBe(hash('sha256', $fixture['token']))
        ->not->toContain($fixture['token'])
        ->and($fixture['grant']->token_expires_at->isFuture())->toBeTrue();

    $unverified = User::factory()->unverified()->create();
    expect(fn () => app(OrganisationContext::class)->run(
        $fixture['organisation'],
        fn () => app(IssuePortalAccessGrant::class)->handle($fixture['party'], $unverified, $fixture['staff']),
    ))->toThrow(LogicException::class, 'verified User');

    $replacement = app(OrganisationContext::class)->run(
        $fixture['organisation'],
        fn (): array => app(IssuePortalAccessGrant::class)->handle($fixture['party'], $fixture['supporter'], $fixture['staff']),
    );
    expect($fixture['grant']->refresh()->revoked_at)->not->toBeNull()
        ->and($replacement['grant']->revoked_at)->toBeNull();
});

it('binds portal verification to the tenant host and rejects reused or expired links', function () {
    $fixture = supporterPortalFixture();
    $crossTenantUrl = "https://{$fixture['otherOrganisation']->slug}.community-kind.test/portal/access/{$fixture['token']}";
    $url = supporterPortalUrl($fixture, "/portal/access/{$fixture['token']}");

    $this->get($crossTenantUrl)->assertNotFound();
    $this->get($url)
        ->assertRedirect(supporterPortalUrl($fixture))
        ->assertSessionHas('portal_access_grant_id', $fixture['grant']->id);
    $this->assertAuthenticatedAs($fixture['supporter']);
    $this->get($url)->assertGone();

    $expired = app(OrganisationContext::class)->run(
        $fixture['organisation'],
        fn (): array => app(IssuePortalAccessGrant::class)->handle($fixture['party'], $fixture['supporter'], $fixture['staff']),
    );
    app(OrganisationContext::class)->run(
        $fixture['organisation'],
        fn () => $expired['grant']->update(['token_expires_at' => now()->subMinute()]),
    );
    $this->get(supporterPortalUrl($fixture, "/portal/access/{$expired['token']}"))->assertGone();
});

it('shows only the linked supporter projection and no staff or other-party data', function () {
    $fixture = supporterPortalFixture();
    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        $donation = Donation::factory()->create(['party_id' => $fixture['party']->id]);
        RecurringMandate::factory()->create([
            'donation_id' => $donation->id,
            'party_id' => $fixture['party']->id,
            'status' => RecurringMandateStatus::Active,
        ]);
        $registration = SupporterRegistration::factory()->create([
            'party_id' => $fixture['party']->id,
            'kind' => SupporterRegistrationKind::Volunteer,
            'title' => 'Saturday food garden',
            'status' => SupporterRegistrationStatus::Confirmed,
        ]);
        $opportunity = VolunteerOpportunity::factory()->create();
        $application = VolunteerApplication::factory()->create([
            'volunteer_opportunity_id' => $opportunity->id,
            'party_id' => $fixture['party']->id,
            'supporter_registration_id' => $registration->id,
            'status' => VolunteerApplicationStatus::Approved,
        ]);
        VolunteerCredential::factory()->create(['volunteer_application_id' => $application->id, 'party_id' => $fixture['party']->id]);
        $shift = VolunteerShift::factory()->create(['volunteer_opportunity_id' => $opportunity->id]);
        VolunteerAssignment::factory()->create(['volunteer_shift_id' => $shift->id, 'volunteer_application_id' => $application->id, 'party_id' => $fixture['party']->id, 'status' => VolunteerAssignmentStatus::Confirmed]);
    });

    $this->get(supporterPortalUrl($fixture, "/portal/access/{$fixture['token']}"));
    $this->get(supporterPortalUrl($fixture))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('portal/show')
            ->where('auth.user', null)
            ->where('organisations', [])
            ->where('currentOrganisation', null)
            ->where('profile.displayName', 'Amina Supporter')
            ->where('profile.email', 'amina@example.test')
            ->has('recurringMandates', 1)
            ->where('registrations.0.title', 'Saturday food garden')
            ->where('registrations.0.volunteer.applicationStatus', 'approved')
            ->has('registrations.0.volunteer.credentials', 1)
            ->has('registrations.0.volunteer.assignments', 1)
            ->missing('registrations.0.volunteer.cases')
            ->missing('party')
            ->missing('roles')
            ->missing('programs')
            ->missing('cases'));
    $this->get('/')->assertNotFound();
    $this->get("https://{$fixture['otherOrganisation']->slug}.community-kind.test/portal")->assertNotFound();
});

it('lets the supporter update safe profile fields and preferences which immediately affect segments', function () {
    $fixture = supporterPortalFixture();
    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        app(RecordPartyConsent::class)->handle($fixture['party'], [
            'purpose' => ConsentPurpose::SupporterUpdates,
            'channel' => ConsentChannel::Email,
            'decision' => ConsentDecision::Granted,
            'wording_version' => 'fixture-v1',
            'wording' => 'Email updates',
            'source' => 'fixture',
            'occurred_at' => now()->subMinute()->toAtomString(),
        ], $fixture['staff']);
    });
    $this->get(supporterPortalUrl($fixture, "/portal/access/{$fixture['token']}"));

    $this->patch(supporterPortalUrl($fixture, '/portal/profile'), [
        'display_name' => 'Amina Updated',
        'email' => 'updated@example.test',
        'telephone' => null,
        'roles' => ['client'],
    ])->assertRedirect();
    $this->put(supporterPortalUrl($fixture, '/portal/consent-preferences'), ['channels' => []])->assertRedirect();

    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        $party = $fixture['party']->refresh();
        $segment = AudienceSegment::factory()->create([
            'criteria' => [
                'purpose' => ConsentPurpose::SupporterUpdates->value,
                'channel' => ConsentChannel::Email->value,
                'role' => PartyBusinessRole::Donor->value,
                'service_area' => null,
                'interest' => null,
                'donation_activity' => false,
                'campaign_source' => null,
            ],
        ]);
        expect($party->display_name)->toBe('Amina Updated')
            ->and($party->businessRoles()->pluck('role')->all())->toBe([PartyBusinessRole::Donor])
            ->and(app(EvaluateAudienceSegment::class)->handle($segment))->toBeEmpty()
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::SupporterProfileUpdated)->exists())->toBeTrue()
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::SupporterConsentPreferencesUpdated)->exists())->toBeTrue();
    });
});

it('restricts cancellation to the linked supporter records', function () {
    $fixture = supporterPortalFixture();
    [$mandate, $registration, $otherRegistration] = app(OrganisationContext::class)->run(
        $fixture['organisation'],
        function () use ($fixture): array {
            $donation = Donation::factory()->create(['party_id' => $fixture['party']->id]);
            $mandate = RecurringMandate::factory()->create([
                'donation_id' => $donation->id,
                'party_id' => $fixture['party']->id,
                'status' => RecurringMandateStatus::Active,
            ]);
            $registration = SupporterRegistration::factory()->create(['party_id' => $fixture['party']->id]);
            $otherRegistration = SupporterRegistration::factory()->create(['party_id' => $fixture['otherParty']->id]);

            return [$mandate, $registration, $otherRegistration];
        },
    );
    $this->get(supporterPortalUrl($fixture, "/portal/access/{$fixture['token']}"));

    $this->delete(supporterPortalUrl($fixture, "/portal/recurring-mandates/{$mandate->id}"))->assertRedirect();
    $this->delete(supporterPortalUrl($fixture, "/portal/recurring-mandates/{$mandate->id}"))->assertRedirect();
    $this->delete(supporterPortalUrl($fixture, "/portal/registrations/{$registration->id}"))->assertRedirect();
    $this->delete(supporterPortalUrl($fixture, "/portal/registrations/{$otherRegistration->id}"))->assertNotFound();

    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($mandate, $registration): void {
        expect($mandate->refresh()->status)->toBe(RecurringMandateStatus::Cancelled)
            ->and($registration->refresh()->status)->toBe(SupporterRegistrationStatus::Cancelled);
    });
});

it('revokes the grant, signs the supporter out, and rejects stale sessions', function () {
    $fixture = supporterPortalFixture();
    $this->get(supporterPortalUrl($fixture, "/portal/access/{$fixture['token']}"));

    $this->delete(supporterPortalUrl($fixture, '/portal/access'))
        ->assertRedirect("https://{$fixture['organisation']->slug}.community-kind.test");
    $this->assertGuest();
    expect($fixture['grant']->refresh()->revoked_at)->not->toBeNull();
    $this->get(supporterPortalUrl($fixture))->assertNotFound();
});

it('allows only supporter-safe engagement staff to issue a portal link', function () {
    $fixture = supporterPortalFixture();
    $caseWorker = User::factory()->create();
    $fixture['organisation']->memberships()->create(['user_id' => $caseWorker->id, 'role' => OrganisationRole::CaseWorker]);
    $url = route('parties.portal-access-grants.store', [$fixture['organisation'], $fixture['party']]);

    $this->actingAs($caseWorker)->post($url, ['email' => $fixture['supporter']->email])->assertForbidden();
    $this->actingAs($fixture['staff'])->post($url, ['email' => $fixture['supporter']->email])
        ->assertRedirect()
        ->assertSessionHas('portal_access_url');
});
