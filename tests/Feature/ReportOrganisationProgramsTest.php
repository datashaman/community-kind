<?php

use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Models\Organisation;
use App\Models\OrganisationAccessHold;
use App\Models\Program;
use App\OrganisationContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

it('reports only the requested Organisation and leaves no tenant context behind', function () {
    Cache::flush();
    $firstOrganisation = Organisation::factory()->active()->create(['slug' => 'first']);
    $secondOrganisation = Organisation::factory()->active()->create(['slug' => 'second']);
    $context = app(OrganisationContext::class);
    $context->run($firstOrganisation, fn (): Program => Program::factory()->for($firstOrganisation)->create(['name' => 'First Program']));
    $context->run($secondOrganisation, fn (): Program => Program::factory()->for($secondOrganisation)->create(['name' => 'Secret Program']));

    expect(Artisan::call('organisations:program-report', ['organisation' => 'first']))->toBe(0)
        ->and(Artisan::output())->toContain('"organisation_id":'.$firstOrganisation->id)
        ->toContain('First Program')
        ->not->toContain('Secret Program');

    expect(fn () => $context->id())->toThrow(LogicException::class);
});

it('fails commands for unknown Organisations and restricted command scope', function () {
    $organisation = Organisation::factory()->active()->create();
    OrganisationAccessHold::factory()->create([
        'organisation_id' => $organisation->id,
        'scope' => OrganisationAccessScope::Commands,
        'access_level' => OrganisationAccessLevel::Denied,
    ]);

    $this->artisan('organisations:program-report', ['organisation' => 'missing'])
        ->expectsOutput('Organisation not found.')
        ->assertFailed();
    $this->artisan('organisations:program-report', ['organisation' => $organisation->slug])
        ->expectsOutput('Organisation commands are unavailable for this Organisation.')
        ->assertFailed();
});
