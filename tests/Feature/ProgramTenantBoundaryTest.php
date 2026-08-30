<?php

use App\Actions\Programs\BuildProgramReport;
use App\Actions\Programs\ExportPrograms;
use App\Actions\Programs\SearchPrograms;
use App\Enums\OrganisationRole;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\User;
use App\OrganisationContext;
use App\OrganisationStorage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

function createProgramBoundaryFixture(): array
{
    $user = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $otherOrganisation = Organisation::factory()->active()->create();
    $membership = $organisation->memberships()->create([
        'user_id' => $user->id,
        'role' => OrganisationRole::ProgramManager,
    ]);
    $program = app(OrganisationContext::class)->run(
        $organisation,
        fn (): Program => Program::factory()->for($organisation)->create(['name' => 'Neighbour Support', 'slug' => 'neighbour-support']),
    );
    $otherProgram = app(OrganisationContext::class)->run(
        $otherOrganisation,
        fn (): Program => Program::factory()->for($otherOrganisation)->create(['name' => 'Neighbour Secret', 'slug' => 'neighbour-secret']),
    );
    app(OrganisationContext::class)->run(
        $organisation,
        fn () => $membership->programs()->attach($program),
    );

    return compact('user', 'organisation', 'otherOrganisation', 'membership', 'program', 'otherProgram');
}

it('returns not found for cross-Organisation Program identifiers and permits an assigned Program', function () {
    extract(createProgramBoundaryFixture());

    $this->actingAs($user)
        ->getJson(route('organisations.programs.show', [$organisation, $program->id]))
        ->assertOk()
        ->assertJsonPath('id', $program->id);

    $this->actingAs($user)
        ->getJson(route('organisations.programs.show', [$organisation, $otherProgram->id]))
        ->assertNotFound();
    $this->actingAs($user)
        ->getJson(route('organisations.programs.show', [$organisation, $otherProgram->slug]))
        ->assertNotFound();

    $this->actingAs($user)
        ->getJson(route('organisations.programs.report', $organisation))
        ->assertForbidden();
});

it('allows Organisation administrators to use tenant-scoped search reports and exports', function () {
    $administrator = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $otherOrganisation = Organisation::factory()->active()->create();
    $organisation->memberships()->create([
        'user_id' => $administrator->id,
        'role' => OrganisationRole::OrganisationAdministrator,
    ]);
    app(OrganisationContext::class)->run(
        $organisation,
        fn (): Program => Program::factory()->for($organisation)->create(['name' => 'Visible Program']),
    );
    app(OrganisationContext::class)->run(
        $otherOrganisation,
        fn (): Program => Program::factory()->for($otherOrganisation)->create(['name' => 'Hidden Program']),
    );
    Storage::fake();

    $this->actingAs($administrator)
        ->getJson(route('organisations.programs.search', [$organisation, 'query' => 'Program']))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'Visible Program');
    $this->actingAs($administrator)
        ->getJson(route('organisations.programs.report', $organisation))
        ->assertOk()
        ->assertJsonPath('program_count', 1)
        ->assertJsonMissing(['Hidden Program']);
    $export = $this->actingAs($administrator)
        ->get(route('organisations.programs.export', $organisation))
        ->assertOk();

    expect($export->streamedContent())->toContain('Visible Program')->not->toContain('Hidden Program');
});

it('does not let ownership alone grant operational Program access', function () {
    $owner = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $organisation->memberships()->create(['user_id' => $owner->id, 'is_owner' => true]);
    $program = app(OrganisationContext::class)->run(
        $organisation,
        fn (): Program => Program::factory()->for($organisation)->create(),
    );

    $this->actingAs($owner)
        ->getJson(route('organisations.programs.show', [$organisation, $program]))
        ->assertForbidden();
    $this->actingAs($owner)
        ->getJson(route('organisations.programs.report', $organisation))
        ->assertForbidden();
});

it('updates only a Program assigned to an authorised Program manager', function () {
    extract(createProgramBoundaryFixture());

    $this->actingAs($user)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => 'Updated Support',
            'slug' => 'updated-support',
            'organisation_id' => $otherOrganisation->id,
        ])
        ->assertOk()
        ->assertJsonPath('name', 'Updated Support')
        ->assertJsonPath('organisation_id', $organisation->id);

    expect(DB::table('programs')->where('id', $program->id)->value('organisation_id'))
        ->toBe($organisation->id);
});

it('validates Program slug uniqueness within the current Organisation', function () {
    extract(createProgramBoundaryFixture());
    $secondProgram = app(OrganisationContext::class)->run(
        $organisation,
        fn (): Program => Program::factory()->for($organisation)->create(['slug' => 'second-program']),
    );

    $this->actingAs($user)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => $program->name,
            'slug' => $secondProgram->slug,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

it('scopes search reports exports caches and soft deletes to the current Organisation', function () {
    extract(createProgramBoundaryFixture());
    Storage::fake();

    app(OrganisationContext::class)->run($organisation, function () use ($organisation, $program, $otherProgram): void {
        $searchResults = app(SearchPrograms::class)->handle('Neighbour');
        $report = app(BuildProgramReport::class)->handle();
        $path = app(ExportPrograms::class)->handle();

        expect($searchResults->pluck('id')->all())->toBe([$program->id])
            ->and($report)->toMatchArray([
                'organisation_id' => $organisation->id,
                'program_count' => 1,
                'program_names' => ['Neighbour Support'],
            ])
            ->and($path)->toBe("organisations/{$organisation->id}/exports/programs.csv")
            ->and(Storage::get($path))->toContain('Neighbour Support')->not->toContain('Neighbour Secret');

        expect(Program::query()->whereKey($otherProgram->id)->exists())->toBeFalse();

        $program->delete();

        expect(Program::query()->count())->toBe(0)
            ->and(app(SearchPrograms::class)->handle('Neighbour'))->toHaveCount(0);
    });
});

it('rejects cross-Organisation membership associations at the database boundary', function () {
    extract(createProgramBoundaryFixture());

    expect(fn () => DB::table('membership_program')->insert([
        'organisation_id' => $organisation->id,
        'membership_id' => $membership->id,
        'program_id' => $otherProgram->id,
    ]))->toThrow(QueryException::class);
});

it('does not report a tenant file as stored when the filesystem write fails', function () {
    $organisation = Organisation::factory()->active()->create();
    Storage::shouldReceive('put')->once()->andReturnFalse();

    app(OrganisationContext::class)->run($organisation, function (): void {
        expect(fn () => app(OrganisationStorage::class)->put('exports/programs.csv', 'contents'))
            ->toThrow(RuntimeException::class, 'Unable to write');
    });
});
