<?php

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Actions\CaseConfidentiality\GrantRestrictedAccess;
use App\Actions\CaseConfidentiality\ReclassifyServiceCase;
use App\Actions\CaseConfidentiality\RecordCaseRiskAssessment;
use App\Actions\CaseConfidentiality\RevokeRestrictedAccess;
use App\Actions\Intake\AssignCaseWorker;
use App\Actions\Intake\TransitionIntakeRequest;
use App\Actions\Parties\StoreSafeContactInstruction;
use App\Data\Values\ClassifiedValue;
use App\Enums\CaseClassification;
use App\Enums\IntakeStatus;
use App\Enums\OrganisationRole;
use App\Enums\PartyBusinessRole;
use App\Enums\RestrictedAccessPermission;
use App\Enums\TenantAuditEventType;
use App\Models\IntakeRequest;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyRole;
use App\Models\Program;
use App\Models\RestrictedAccessGrant;
use App\Models\ServiceCase;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\OrganisationContext;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;

/** @return array{organisation: Organisation, program: Program, party: Party, case: ServiceCase, manager: User, managerMembership: Membership, worker: User, workerMembership: Membership, engagement: User, administrator: User, owner: User, executive: User} */
function caseConfidentialityFixture(): array
{
    $organisation = Organisation::factory()->active()->create();
    $program = app(OrganisationContext::class)->run($organisation, fn (): Program => Program::factory()->for($organisation)->create(['case_default_classification' => CaseClassification::HighlyRestricted]));
    $users = collect(['manager', 'worker', 'engagement', 'administrator', 'owner', 'executive'])
        ->mapWithKeys(fn (string $key): array => [$key => User::factory()->create()]);
    $managerMembership = $organisation->memberships()->create(['user_id' => $users['manager']->id, 'role' => OrganisationRole::ProgramManager]);
    $workerMembership = $organisation->memberships()->create(['user_id' => $users['worker']->id, 'role' => OrganisationRole::CaseWorker]);
    $organisation->memberships()->create(['user_id' => $users['engagement']->id, 'role' => OrganisationRole::EngagementOfficer]);
    $organisation->memberships()->create(['user_id' => $users['administrator']->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $organisation->memberships()->create(['user_id' => $users['owner']->id, 'is_owner' => true]);
    $organisation->memberships()->create(['user_id' => $users['executive']->id, 'role' => OrganisationRole::ExecutiveViewer]);

    [$party, $case] = app(OrganisationContext::class)->run($organisation, function () use ($organisation, $program): array {
        $party = Party::factory()->for($organisation)->create(['display_name' => 'Dual Role Person']);
        $party->programs()->attach($program);
        PartyRole::factory()->for($party)->create(['role' => PartyBusinessRole::Client]);
        PartyRole::factory()->for($party)->create(['role' => PartyBusinessRole::Volunteer]);
        $intake = IntakeRequest::factory()->create(['program_id' => $program->id, 'party_id' => $party->id]);
        $case = ServiceCase::factory()->create([
            'intake_request_id' => $intake->id,
            'program_id' => $program->id,
            'party_id' => $party->id,
        ]);

        return [$party, $case];
    });
    app(OrganisationContext::class)->run($organisation, function () use ($managerMembership, $program, $workerMembership): void {
        $managerMembership->programs()->attach($program);
        $workerMembership->programs()->attach($program);
    });

    return [
        'organisation' => $organisation,
        'program' => $program,
        'party' => $party,
        'case' => $case,
        'manager' => $users['manager'],
        'managerMembership' => $managerMembership,
        'worker' => $users['worker'],
        'workerMembership' => $workerMembership,
        'engagement' => $users['engagement'],
        'administrator' => $users['administrator'],
        'owner' => $users['owner'],
        'executive' => $users['executive'],
    ];
}

it('defaults Case content to confidential and audits every reasoned reclassification', function () {
    $fixture = caseConfidentialityFixture();

    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        expect($fixture['case']->confidentiality)->toBe(CaseClassification::Confidential);
        $intake = IntakeRequest::factory()->create([
            'program_id' => $fixture['program']->id,
            'party_id' => $fixture['party']->id,
            'status' => IntakeStatus::UnderReview,
            'version' => 3,
            'encrypted_content' => new ClassifiedValue(json_encode([
                'narrative' => 'Synthetic request.',
                'presenting_needs' => 'Support.',
                'intake_fields' => [],
                'service_consent' => ['granted' => true, 'source' => 'test', 'captured_at' => now()->toAtomString()],
            ], JSON_THROW_ON_ERROR)),
        ]);
        app(TransitionIntakeRequest::class)->handle($intake, IntakeStatus::Accepted, 3, $fixture['manager']);
        expect($intake->refresh()->serviceCase->confidentiality)->toBe(CaseClassification::HighlyRestricted);

        app(ReclassifyServiceCase::class)->handle($fixture['case'], CaseClassification::HighlyRestricted, 'Detailed risk recorded.', $fixture['manager']);
        $audit = TenantAuditEvent::query()->where('type', TenantAuditEventType::CaseReclassified)->sole();
        expect($fixture['case']->refresh()->confidentiality)->toBe(CaseClassification::HighlyRestricted)
            ->and($audit->payload)->toBe([
                'case_id' => $fixture['case']->id,
                'from' => 'confidential',
                'to' => 'highly_restricted',
                'reason' => 'Detailed risk recorded.',
            ]);
        expect(fn () => app(ReclassifyServiceCase::class)->handle($fixture['case'], CaseClassification::Confidential, 'No longer sensitive.', $fixture['manager']))
            ->toThrow(LogicException::class, 'requires current restricted access');
        app(GrantRestrictedAccess::class)->handle($fixture['case'], $fixture['managerMembership'], RestrictedAccessPermission::SensitiveData, 'Reclassification review.', $fixture['manager']);
        app(ReclassifyServiceCase::class)->handle($fixture['case'], CaseClassification::Confidential, 'Risk was resolved.', $fixture['manager']);
        expect($fixture['case']->refresh()->confidentiality)->toBe(CaseClassification::Confidential)
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::CaseReclassified)->count())->toBe(2);
    });
});

it('enforces role Program assignment and restricted-access boundaries for every MVP role', function () {
    $fixture = caseConfidentialityFixture();
    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        app(AssignCaseWorker::class)->handle($fixture['case'], $fixture['workerMembership'], $fixture['manager']);
        app(ReclassifyServiceCase::class)->handle($fixture['case'], CaseClassification::HighlyRestricted, 'Sensitive fixture.', $fixture['manager']);
    });

    foreach (['manager', 'worker', 'engagement', 'administrator', 'owner', 'executive'] as $actor) {
        $this->actingAs($fixture[$actor])->get(route('cases.show', [$fixture['organisation'], $fixture['case']]))->assertForbidden();
    }

    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        app(GrantRestrictedAccess::class)->handle($fixture['case'], $fixture['managerMembership'], RestrictedAccessPermission::SensitiveData, 'Manager safeguarding duty.', $fixture['manager']);
        app(GrantRestrictedAccess::class)->handle($fixture['case'], $fixture['workerMembership'], RestrictedAccessPermission::SensitiveData, 'Assigned worker safeguarding duty.', $fixture['manager']);
    });

    $this->actingAs($fixture['manager'])->get(route('cases.show', [$fixture['organisation'], $fixture['case']]))->assertOk();
    $this->actingAs($fixture['worker'])->get(route('cases.show', [$fixture['organisation'], $fixture['case']]))->assertOk();
    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        $workerGrant = RestrictedAccessGrant::query()
            ->where('membership_id', $fixture['workerMembership']->id)
            ->where('permission', RestrictedAccessPermission::SensitiveData)
            ->sole();
        app(RevokeRestrictedAccess::class)->handle($workerGrant, 'Assignment changed.', $fixture['manager']);
        expect($workerGrant->revocation()->exists())->toBeTrue()
            ->and(TenantAuditEvent::query()->where('type', TenantAuditEventType::RestrictedAccessRevoked)->exists())->toBeTrue();
    });
    $this->actingAs($fixture['worker'])->get(route('cases.show', [$fixture['organisation'], $fixture['case']]))->assertForbidden();
    foreach (['engagement', 'administrator', 'owner', 'executive'] as $actor) {
        $this->actingAs($fixture[$actor])->get(route('cases.show', [$fixture['organisation'], $fixture['case']]))->assertForbidden();
    }

    $otherOrganisation = Organisation::factory()->active()->create();
    $otherManager = User::factory()->create();
    $otherOrganisation->memberships()->create(['user_id' => $otherManager->id, 'role' => OrganisationRole::ProgramManager]);
    $this->actingAs($otherManager)->get(route('cases.show', [$otherOrganisation, $fixture['case']]))->assertNotFound();
});

it('reveals risk and safe-contact values only with Case-specific sensitive access and audits without content', function () {
    $fixture = caseConfidentialityFixture();
    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        app(AssignCaseWorker::class)->handle($fixture['case'], $fixture['workerMembership'], $fixture['manager']);
        app(GrantRestrictedAccess::class)->handle($fixture['case'], $fixture['managerMembership'], RestrictedAccessPermission::SensitiveData, 'Safeguarding review.', $fixture['manager']);
        app(RecordCaseRiskAssessment::class)->handle($fixture['case'], 'Synthetic protected risk detail.', $fixture['manager']);
        app(StoreSafeContactInstruction::class)->handle($fixture['party'], [
            'instruction' => 'Do not leave voicemail.',
            'source' => 'test',
            'effective_at' => now()->toAtomString(),
        ], $fixture['manager']);
    });

    $this->actingAs($fixture['worker'])
        ->get(route('cases.show', [$fixture['organisation'], $fixture['case']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('caseRecord.safeContactBanner', null)
            ->has('caseRecord.riskAssessments', 0));
    $this->actingAs($fixture['manager'])
        ->get(route('cases.show', [$fixture['organisation'], $fixture['case']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('caseRecord.safeContactBanner', 'Do not leave voicemail.')
            ->where('caseRecord.riskAssessments.0.content', 'Synthetic protected risk detail.'));

    app(OrganisationContext::class)->run($fixture['organisation'], function (): void {
        $payloads = TenantAuditEvent::query()->pluck('payload')->map(fn (array $payload): string => json_encode($payload, JSON_THROW_ON_ERROR))->implode(' ');
        expect($payloads)->not->toContain('voicemail')->not->toContain('risk detail');
    });
});

it('gives engagement staff a supporter-safe projection and keeps exports explicit and redacted', function () {
    $fixture = caseConfidentialityFixture();

    $this->actingAs($fixture['engagement'])
        ->get(route('parties.show', [$fixture['organisation'], $fixture['party']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('party.displayName', 'Dual Role Person')
            ->where('party.supporterSafe', true)
            ->where('party.roles', ['volunteer'])
            ->where('party.interests', [])
            ->where('party.programIds', [])
            ->where('party.timeline', [])
            ->where('party.safeContactInstructions', []));
    $this->actingAs($fixture['engagement'])
        ->get(route('parties.index', [$fixture['organisation'], 'query' => 'Dual Role']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('parties.data', 1)
            ->where('parties.data.0.roles', ['Volunteer'])
            ->where('parties.data.0.programs', []));
    $this->actingAs($fixture['administrator'])
        ->get(route('parties.show', [$fixture['organisation'], $fixture['party']]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('party.displayName', 'Administrative Party record')
            ->where('party.email', null)
            ->where('party.roles', [])
            ->where('party.programIds', []));

    $this->actingAs($fixture['manager'])
        ->get(route('programs.cases.export', [$fixture['organisation'], $fixture['program']]))
        ->assertSessionHasErrors('export');

    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        app(GrantRestrictedAccess::class)->handle($fixture['case'], $fixture['managerMembership'], RestrictedAccessPermission::IdentifiableCaseExport, 'Approved synthetic export.', $fixture['manager']);
        app(ReclassifyServiceCase::class)->handle($fixture['case'], CaseClassification::HighlyRestricted, 'Restricted export fixture.', $fixture['manager']);
    });
    $export = $this->actingAs($fixture['manager'])->get(route('programs.cases.export', [$fixture['organisation'], $fixture['program']]));
    $export->assertOk();
    expect($export->streamedContent())
        ->not->toContain('Dual Role Person');

    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        app(GrantRestrictedAccess::class)->handle($fixture['case'], $fixture['managerMembership'], RestrictedAccessPermission::SensitiveData, 'Approved restricted export.', $fixture['manager']);
    });
    $authorizedExport = $this->actingAs($fixture['manager'])->get(route('programs.cases.export', [$fixture['organisation'], $fixture['program']]));
    expect($authorizedExport->streamedContent())
        ->toContain('Dual Role Person')
        ->not->toContain('risk')
        ->not->toContain('voicemail');

    app(OrganisationContext::class)->run($fixture['organisation'], fn () => expect(RestrictedAccessGrant::query()->count())->toBe(2));
});

it('fails closed when a restricted-access audit cannot be persisted', function () {
    $fixture = caseConfidentialityFixture();
    $this->mock(RecordTenantAuditEvent::class, function (MockInterface $mock): void {
        $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable.'));
    });

    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): void {
        expect(fn () => app(GrantRestrictedAccess::class)->handle(
            $fixture['case'],
            $fixture['managerMembership'],
            RestrictedAccessPermission::SensitiveData,
            'Must be audited.',
            $fixture['manager'],
        ))->toThrow(RuntimeException::class, 'Audit unavailable');
        expect(RestrictedAccessGrant::query()->count())->toBe(0);
    });
});
