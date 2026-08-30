<?php

use App\Actions\Organisations\InvalidateOrganisationAccess;
use App\Actions\Programs\BuildProgramReport;
use App\Actions\Programs\ExportPrograms;
use App\Actions\Programs\SearchPrograms;
use App\Models\Organisation;
use App\Models\Program;
use App\OrganisationCache;
use App\OrganisationContext;
use App\OrganisationStorage;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('fails closed when tenant data is queried or created without an Organisation context', function () {
    expect(fn () => Program::query()->count())->toThrow(LogicException::class)
        ->and(fn () => Program::create(['name' => 'No context', 'slug' => 'no-context']))->toThrow(LogicException::class)
        ->and(fn () => app(OrganisationCache::class)->key('programs'))->toThrow(LogicException::class)
        ->and(fn () => app(OrganisationStorage::class)->path('exports/programs.csv'))->toThrow(LogicException::class)
        ->and(fn () => app(SearchPrograms::class)->handle('Program'))->toThrow(LogicException::class)
        ->and(fn () => app(BuildProgramReport::class)->handle())->toThrow(LogicException::class)
        ->and(fn () => app(ExportPrograms::class)->handle())->toThrow(LogicException::class);
});

it('scopes reads and writes to one Organisation and makes ownership immutable', function () {
    $firstOrganisation = Organisation::factory()->active()->create();
    $secondOrganisation = Organisation::factory()->active()->create();
    $context = app(OrganisationContext::class);

    $firstProgram = $context->run($firstOrganisation, fn (): Program => Program::factory()->for($firstOrganisation)->create([
        'name' => 'Shared name',
        'slug' => 'shared-name',
    ]));
    $context->run($secondOrganisation, fn (): Program => Program::factory()->for($secondOrganisation)->create([
        'name' => 'Shared name',
        'slug' => 'shared-name',
    ]));

    $context->run($firstOrganisation, function () use ($firstProgram, $secondOrganisation): void {
        expect(Program::query()->pluck('id')->all())->toBe([$firstProgram->id]);

        $firstProgram->organisation_id = $secondOrganisation->id;

        expect(fn () => $firstProgram->save())->toThrow(LogicException::class, 'immutable');
    });
});

it('rejects mismatched ownership and stale contexts', function () {
    $firstOrganisation = Organisation::factory()->active()->create();
    $secondOrganisation = Organisation::factory()->active()->create();
    $context = app(OrganisationContext::class);

    $context->run($firstOrganisation, function () use ($context, $firstOrganisation, $secondOrganisation): void {
        expect(fn () => Program::factory()->for($secondOrganisation)->create())
            ->toThrow(LogicException::class, 'does not belong');

        app(InvalidateOrganisationAccess::class)->handle($firstOrganisation);

        expect(fn () => $context->organisation())->toThrow(LogicException::class, 'stale')
            ->and(fn () => Program::query()->count())->toThrow(LogicException::class, 'stale')
            ->and(fn () => app(OrganisationCache::class)->key('programs'))->toThrow(LogicException::class, 'stale')
            ->and(fn () => app(OrganisationStorage::class)->path('exports/programs.csv'))->toThrow(LogicException::class, 'stale')
            ->and(fn () => app(SearchPrograms::class)->handle('Program'))->toThrow(LogicException::class, 'stale')
            ->and(fn () => app(BuildProgramReport::class)->handle())->toThrow(LogicException::class, 'stale')
            ->and(fn () => app(ExportPrograms::class)->handle())->toThrow(LogicException::class, 'stale');
    });
});

it('isolates each Organisation during tenant iteration and restores the empty context', function () {
    $organisations = Organisation::factory()->count(2)->active()->create();
    $context = app(OrganisationContext::class);

    foreach ($organisations as $organisation) {
        $context->run($organisation, fn (): Program => Program::factory()->for($organisation)->create());
    }

    $visited = [];
    $context->each(function (Organisation $organisation) use (&$visited): void {
        $visited[$organisation->id] = Program::query()->count();
    });

    expect($visited)->toBe([
        $organisations[0]->id => 1,
        $organisations[1]->id => 1,
    ])->and(fn () => $context->id())->toThrow(LogicException::class);
});

it('enforces scoped Program slug uniqueness in the database', function () {
    $organisation = Organisation::factory()->active()->create();

    app(OrganisationContext::class)->run($organisation, function () use ($organisation): void {
        Program::factory()->for($organisation)->create(['slug' => 'duplicate']);

        expect(fn () => Program::factory()->for($organisation)->create(['slug' => 'duplicate']))
            ->toThrow(QueryException::class);
    });
});

it('rejects Program ownership changes at the database boundary', function () {
    $firstOrganisation = Organisation::factory()->active()->create();
    $secondOrganisation = Organisation::factory()->active()->create();
    $program = app(OrganisationContext::class)->run(
        $firstOrganisation,
        fn (): Program => Program::factory()->for($firstOrganisation)->create(),
    );

    expect(fn () => DB::table('programs')->where('id', $program->id)->update([
        'organisation_id' => $secondOrganisation->id,
    ]))->toThrow(QueryException::class);
});
