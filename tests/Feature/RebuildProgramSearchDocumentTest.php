<?php

use App\Actions\Organisations\InvalidateOrganisationAccess;
use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Enums\OrganisationLifecycleEventType;
use App\Jobs\RebuildProgramSearchDocument;
use App\Models\Organisation;
use App\Models\OrganisationAccessHold;
use App\Models\Program;
use App\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('captures tenant identity and audits a scoped search rebuild', function () {
    $organisation = Organisation::factory()->active()->create();

    app(OrganisationContext::class)->run($organisation, function () use ($organisation): void {
        $program = Program::factory()->for($organisation)->create();
        $job = new RebuildProgramSearchDocument($program);

        expect($job->organisationId)->toBe($organisation->id)
            ->and($job->programId)->toBe($program->id);

        app()->call([$job, 'handle']);
    });

    $this->assertDatabaseHas('organisation_lifecycle_events', [
        'organisation_id' => $organisation->id,
        'type' => OrganisationLifecycleEventType::ProgramSearchRebuilt->value,
    ]);
});

it('rejects a job whose captured Organisation context has become stale', function () {
    $organisation = Organisation::factory()->active()->create();
    $job = app(OrganisationContext::class)->run($organisation, function () use ($organisation): RebuildProgramSearchDocument {
        return new RebuildProgramSearchDocument(Program::factory()->for($organisation)->create());
    });
    app(InvalidateOrganisationAccess::class)->handle($organisation);

    expect(fn () => app()->call([$job, 'handle']))
        ->toThrow(LogicException::class, 'stale');
});

it('rejects tenant jobs when the Jobs scope is restricted', function () {
    $organisation = Organisation::factory()->active()->create();
    $job = app(OrganisationContext::class)->run($organisation, function () use ($organisation): RebuildProgramSearchDocument {
        return new RebuildProgramSearchDocument(Program::factory()->for($organisation)->create());
    });
    OrganisationAccessHold::factory()->create([
        'organisation_id' => $organisation->id,
        'scope' => OrganisationAccessScope::Jobs,
        'access_level' => OrganisationAccessLevel::ReadOnly,
    ]);

    expect(fn () => app()->call([$job, 'handle']))
        ->toThrow(LogicException::class, 'cannot run tenant jobs');
});
