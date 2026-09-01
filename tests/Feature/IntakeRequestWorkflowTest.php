<?php

use App\Actions\Intake\ReversePartyMerge;
use App\Actions\Intake\ReviewPartyDuplicate;
use App\Actions\Intake\TransitionIntakeRequest;
use App\Actions\Parties\StorePartyContact;
use App\Enums\CaseAssignmentStatus;
use App\Enums\ConsentPurpose;
use App\Enums\DuplicateReviewDecision;
use App\Enums\IntakeStatus;
use App\Enums\OrganisationRole;
use App\Enums\PartyContactType;
use App\Models\CaseAssignment;
use App\Models\IntakeRequest;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyDuplicateReview;
use App\Models\Program;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/** @return array{manager: User, managerMembership: Membership, worker: User, workerMembership: Membership, organisation: Organisation, program: Program, party: Party} */
function intakeWorkflowFixture(): array
{
    $manager = User::factory()->create();
    $worker = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $managerMembership = $organisation->memberships()->create(['user_id' => $manager->id, 'role' => OrganisationRole::ProgramManager]);
    $workerMembership = $organisation->memberships()->create(['user_id' => $worker->id, 'role' => OrganisationRole::CaseWorker]);
    [$program, $party] = app(OrganisationContext::class)->run($organisation, function () use ($organisation, $managerMembership, $workerMembership): array {
        $program = Program::factory()->for($organisation)->create();
        $program->intakeFields()->create([
            'key' => 'current_situation',
            'label' => 'Current situation',
            'field_type' => 'textarea',
            'is_required' => true,
            'position' => 0,
        ]);
        $program->eligibilityQuestions()->create([
            'key' => 'service_area',
            'label' => 'Lives in service area',
            'is_required' => false,
            'position' => 0,
        ]);
        $program->riskFlags()->create([
            'key' => 'housing_loss',
            'label' => 'At risk of losing housing',
            'position' => 0,
        ]);
        $managerMembership->programs()->attach($program);
        $workerMembership->programs()->attach($program);
        $party = Party::factory()->for($organisation)->create(['display_name' => 'Amina Example']);
        $party->programs()->attach($program);

        return [$program, $party];
    });

    return compact('manager', 'managerMembership', 'worker', 'workerMembership', 'organisation', 'program', 'party');
}

/** @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function intakeRequestPayload(Program $program, Party $party, array $overrides = []): array
{
    return [
        'program_id' => $program->id,
        'party_uuid' => $party->uuid,
        'source' => 'staff_referral',
        'narrative' => 'A fictional referral from a community partner.',
        'presenting_needs' => 'Amina needs stable housing and tenancy advice.',
        'email' => 'amina@example.test',
        'telephone' => '+27 82 555 0101',
        'intake_fields' => ['current_situation' => 'Temporarily staying with friends.'],
        'risk_flags' => ['housing_loss'],
        'consent_granted' => true,
        'consent_source' => 'verbal',
        'idempotency_key' => 'housing-referral-001',
        ...$overrides,
    ];
}

function createIntakeThroughHttp(object $test, array $fixture, array $overrides = []): IntakeRequest
{
    $test->actingAs($fixture['manager'])
        ->post(route('intakes.store', $fixture['organisation']), intakeRequestPayload($fixture['program'], $fixture['party'], $overrides))
        ->assertRedirect();

    return app(OrganisationContext::class)->run(
        $fixture['organisation'],
        fn (): IntakeRequest => IntakeRequest::query()->where('idempotency_key', $overrides['idempotency_key'] ?? 'housing-referral-001')->firstOrFail(),
    );
}

it('records encrypted Program-defined intake content, service consent, and tenant-local duplicate suggestions', function () {
    $fixture = intakeWorkflowFixture();
    $candidate = app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): Party {
        $candidate = Party::factory()->for($fixture['organisation'])->create(['display_name' => 'Existing Amina']);
        app(StorePartyContact::class)->handle($candidate, PartyContactType::Email, 'amina@example.test');

        return $candidate;
    });
    $otherOrganisation = Organisation::factory()->active()->create();
    app(OrganisationContext::class)->run($otherOrganisation, function () use ($otherOrganisation): void {
        $otherParty = Party::factory()->for($otherOrganisation)->create(['display_name' => 'Other tenant record']);
        app(StorePartyContact::class)->handle($otherParty, PartyContactType::Email, 'amina@example.test');
    });

    $intake = createIntakeThroughHttp($this, $fixture);
    $this->actingAs($fixture['manager'])
        ->post(route('intakes.store', $fixture['organisation']), intakeRequestPayload($fixture['program'], $fixture['party']))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(DB::table('intake_requests')->where('id', $intake->id)->value('encrypted_content'))
        ->not->toContain('stable housing')
        ->not->toContain('Temporarily staying with friends');
    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($intake, $candidate, $fixture): void {
        expect(IntakeRequest::query()->where('idempotency_key', 'housing-referral-001')->count())->toBe(1)
            ->and($intake->transitions()->count())->toBe(1)
            ->and($intake->duplicateReviews()->count())->toBe(1)
            ->and($intake->duplicateReviews()->firstOrFail()->candidate_party_id)->toBe($candidate->id)
            ->and($fixture['party']->consents()->where('purpose', ConsentPurpose::Service)->count())->toBe(0);
    });
    $this->actingAs($fixture['manager'])
        ->get(route('intakes.show', [$fixture['organisation'], $intake]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('intakes/show')
            ->where('intake.presentingNeeds', 'Amina needs stable housing and tenancy advice.')
            ->where('intake.duplicateReviews.0.candidate.displayName', 'Existing Amina')
            ->missing('intake.duplicateReviews.0.candidate.services'));
});

it('validates fields and risk flags against the selected Program configuration', function () {
    $fixture = intakeWorkflowFixture();

    $this->actingAs($fixture['manager'])
        ->post(route('intakes.store', $fixture['organisation']), intakeRequestPayload($fixture['program'], $fixture['party'], [
            'intake_fields' => ['unexpected' => 'not allowed'],
            'risk_flags' => ['unconfigured_risk'],
        ]))
        ->assertSessionHasErrors(['intake_fields', 'intake_fields.current_situation', 'risk_flags.0']);
});

it('accepts an intake exactly once and atomically creates and assigns one open case', function () {
    $fixture = intakeWorkflowFixture();
    $intake = createIntakeThroughHttp($this, $fixture);

    foreach ([IntakeStatus::Submitted, IntakeStatus::UnderReview] as $status) {
        $this->actingAs($fixture['manager'])->post(route('intakes.transitions.store', [$fixture['organisation'], $intake]), [
            'status' => $status->value,
            'expected_version' => $intake->version,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $intake->refresh();
    }
    $this->actingAs($fixture['manager'])->post(route('intakes.transitions.store', [$fixture['organisation'], $intake]), [
        'status' => IntakeStatus::Accepted->value,
        'expected_version' => $intake->version,
        'urgency' => 'priority',
        'eligibility_status' => 'eligible',
        'eligibility_context' => ['service_area' => true],
        'risk_flags' => ['housing_loss'],
        'worker_membership_id' => $fixture['workerMembership']->id,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $intake->refresh();
    $case = app(OrganisationContext::class)->run($fixture['organisation'], fn (): ServiceCase => $intake->serviceCase()->firstOrFail());

    expect($intake->status)->toBe(IntakeStatus::Accepted);
    app(OrganisationContext::class)->run($fixture['organisation'], fn () => expect($intake->transitions()->count())->toBe(4)
        ->and($case->assignments()->where('status', CaseAssignmentStatus::Active)->count())->toBe(1)
        ->and($intake->party->consents()->where('purpose', ConsentPurpose::Service)->count())->toBe(1));
    $this->actingAs($fixture['worker'])
        ->get(route('intakes.show', [$fixture['organisation'], $intake]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canTransition', false));

    $this->actingAs($fixture['manager'])->post(route('intakes.transitions.store', [$fixture['organisation'], $intake]), [
        'status' => IntakeStatus::Accepted->value,
        'expected_version' => 3,
        'worker_membership_id' => $fixture['workerMembership']->id,
    ])->assertRedirect();
    app(OrganisationContext::class)->run($fixture['organisation'], fn () => expect(ServiceCase::query()->where('intake_request_id', $intake->id)->count())->toBe(1)
        ->and(CaseAssignment::query()->where('service_case_id', $case->id)->count())->toBe(1));
});

it('rejects stale or invalid decisions and preserves immutable transition history', function () {
    $fixture = intakeWorkflowFixture();
    $intake = createIntakeThroughHttp($this, $fixture);

    $this->actingAs($fixture['manager'])->post(route('intakes.transitions.store', [$fixture['organisation'], $intake]), [
        'status' => IntakeStatus::Accepted->value,
        'expected_version' => 1,
    ])->assertSessionHasErrors('status');
    $this->actingAs($fixture['manager'])->post(route('intakes.transitions.store', [$fixture['organisation'], $intake]), [
        'status' => IntakeStatus::Submitted->value,
        'expected_version' => 99,
    ])->assertSessionHasErrors('status');
    expect(fn () => DB::transaction(fn () => DB::table('intake_transitions')->where('intake_request_id', $intake->id)->update(['reason' => 'rewritten'])))
        ->toThrow(QueryException::class);
});

it('requires consent before acceptance and reasons for redirect or decline', function () {
    $fixture = intakeWorkflowFixture();
    $intake = createIntakeThroughHttp($this, $fixture, [
        'consent_granted' => false,
        'consent_source' => null,
        'idempotency_key' => 'without-consent',
    ]);
    foreach ([IntakeStatus::Submitted, IntakeStatus::UnderReview] as $status) {
        $this->actingAs($fixture['manager'])->post(route('intakes.transitions.store', [$fixture['organisation'], $intake]), [
            'status' => $status->value,
            'expected_version' => $intake->version,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $intake->refresh();
    }

    $this->actingAs($fixture['manager'])->post(route('intakes.transitions.store', [$fixture['organisation'], $intake]), [
        'status' => IntakeStatus::Accepted->value,
        'expected_version' => $intake->version,
    ])->assertSessionHasErrors('status');
    $this->actingAs($fixture['manager'])->post(route('intakes.transitions.store', [$fixture['organisation'], $intake]), [
        'status' => IntakeStatus::Redirected->value,
        'expected_version' => $intake->version,
    ])->assertSessionHasErrors('status');
});

it('transfers primary responsibility atomically and prevents assignment history rewrites', function () {
    $fixture = intakeWorkflowFixture();
    $replacement = User::factory()->create();
    $replacementMembership = $fixture['organisation']->memberships()->create(['user_id' => $replacement->id, 'role' => OrganisationRole::CaseWorker]);
    app(OrganisationContext::class)->run($fixture['organisation'], fn () => $replacementMembership->programs()->attach($fixture['program']));
    $intake = createIntakeThroughHttp($this, $fixture);
    foreach ([IntakeStatus::Submitted, IntakeStatus::UnderReview, IntakeStatus::Accepted] as $status) {
        $this->actingAs($fixture['manager'])->post(route('intakes.transitions.store', [$fixture['organisation'], $intake]), [
            'status' => $status->value,
            'expected_version' => $intake->version,
            'worker_membership_id' => $status === IntakeStatus::Accepted ? $fixture['workerMembership']->id : null,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $intake->refresh();
    }

    $unauthorisedWorker = User::factory()->create();
    $unauthorisedMembership = $fixture['organisation']->memberships()->create(['user_id' => $unauthorisedWorker->id, 'role' => OrganisationRole::CaseWorker]);
    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture, $unauthorisedMembership): void {
        $otherProgram = Program::factory()->for($fixture['organisation'])->create();
        $unauthorisedMembership->programs()->attach($otherProgram);
    });
    $this->actingAs($fixture['manager'])->post(route('intakes.assignments.store', [$fixture['organisation'], $intake]), [
        'membership_id' => $unauthorisedMembership->id,
    ])->assertSessionHasErrors('membership_id');

    $this->actingAs($fixture['manager'])->post(route('intakes.assignments.store', [$fixture['organisation'], $intake]), [
        'membership_id' => $replacementMembership->id,
        'reason' => 'caseload_transfer',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $assignments = app(OrganisationContext::class)->run($fixture['organisation'], fn () => $intake->serviceCase->assignments()->orderBy('started_at')->get());
    expect($assignments)->toHaveCount(2)
        ->and($assignments[0]->status)->toBe(CaseAssignmentStatus::Ended)
        ->and($assignments[0]->ended_reason)->toBe('caseload_transfer')
        ->and($assignments[1]->membership_id)->toBe($replacementMembership->id);
    expect(fn () => DB::transaction(fn () => DB::table('case_assignments')->where('id', $assignments[0]->id)->update(['membership_id' => $replacementMembership->id])))
        ->toThrow(QueryException::class);
});

it('keeps duplicate decisions controlled, idempotent, and reversible before acceptance', function () {
    $fixture = intakeWorkflowFixture();
    $candidate = app(OrganisationContext::class)->run($fixture['organisation'], function () use ($fixture): Party {
        $candidate = Party::factory()->for($fixture['organisation'])->create();
        app(StorePartyContact::class)->handle($candidate, PartyContactType::Email, 'amina@example.test');

        return $candidate;
    });
    $intake = createIntakeThroughHttp($this, $fixture);
    $review = app(OrganisationContext::class)->run($fixture['organisation'], fn (): PartyDuplicateReview => $intake->duplicateReviews()->firstOrFail());

    app(OrganisationContext::class)->run($fixture['organisation'], function () use ($review, $fixture, $candidate, $intake): void {
        app(ReviewPartyDuplicate::class)->handle($review, DuplicateReviewDecision::Merged, $fixture['manager']);
        app(ReviewPartyDuplicate::class)->handle($review->refresh(), DuplicateReviewDecision::Merged, $fixture['manager']);
        expect($intake->refresh()->party_id)->toBe($candidate->id);

        $secondCandidate = Party::factory()->for($fixture['organisation'])->create();
        $secondReview = PartyDuplicateReview::query()->create([
            'organisation_id' => $fixture['organisation']->id,
            'intake_request_id' => $intake->id,
            'submitted_party_id' => $fixture['party']->id,
            'candidate_party_id' => $secondCandidate->id,
            'matched_fields' => ['email'],
        ]);
        expect(fn () => app(ReviewPartyDuplicate::class)->handle($secondReview, DuplicateReviewDecision::Merged, $fixture['manager']))
            ->toThrow(LogicException::class, 'already linked to a different canonical Party');

        $decidedBy = $review->refresh()->decided_by_user_id;
        app(ReversePartyMerge::class)->handle($review->refresh(), $fixture['manager']);
        expect($intake->refresh()->party_id)->toBe($fixture['party']->id)
            ->and($review->refresh()->decided_by_user_id)->toBe($decidedBy)
            ->and($review->reversed_by_user_id)->toBe($fixture['manager']->id)
            ->and($candidate->trashed())->toBeFalse();

        app(ReviewPartyDuplicate::class)->handle($secondReview, DuplicateReviewDecision::Merged, $fixture['manager']);
        app(TransitionIntakeRequest::class)->handle($intake->refresh(), IntakeStatus::Submitted, 1, $fixture['manager']);
        app(TransitionIntakeRequest::class)->handle($intake->refresh(), IntakeStatus::UnderReview, 2, $fixture['manager']);
        app(TransitionIntakeRequest::class)->handle($intake->refresh(), IntakeStatus::Accepted, 3, $fixture['manager']);
        expect(fn () => app(ReversePartyMerge::class)->handle($secondReview->refresh(), $fixture['manager']))
            ->toThrow(LogicException::class, 'cannot be reversed after a Case has been created');
    });
});

it('enforces role, Program, assignment, and cross-Organisation boundaries', function () {
    $fixture = intakeWorkflowFixture();
    $administrator = User::factory()->create();
    $fixture['organisation']->memberships()->create(['user_id' => $administrator->id, 'role' => OrganisationRole::OrganisationAdministrator]);
    $intake = createIntakeThroughHttp($this, $fixture);
    $otherOrganisation = Organisation::factory()->active()->create();
    $otherManager = User::factory()->create();
    $otherOrganisation->memberships()->create(['user_id' => $otherManager->id, 'role' => OrganisationRole::ProgramManager]);

    $this->actingAs($administrator)->get(route('intakes.show', [$fixture['organisation'], $intake]))->assertForbidden();
    $this->actingAs($fixture['worker'])->get(route('intakes.show', [$fixture['organisation'], $intake]))->assertForbidden();
    $this->actingAs($fixture['worker'])
        ->post(route('intakes.store', $fixture['organisation']), intakeRequestPayload($fixture['program'], $fixture['party'], ['idempotency_key' => 'worker-created']))
        ->assertRedirect(route('intakes.index', $fixture['organisation']));
    $this->actingAs($otherManager)->get(route('intakes.show', [$otherOrganisation, $intake]))->assertNotFound();
});
