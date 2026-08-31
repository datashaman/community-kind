<?php

use App\Actions\Auditing\RecordTenantAuditEvent;
use App\Enums\OrganisationRole;
use App\Enums\TenantAuditEventType;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('records an allowlisted tenant audit in the same transaction as a protected Program update', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $membership = $organisation->memberships()->create([
        'user_id' => $user->id,
        'role' => OrganisationRole::ProgramManager,
    ]);
    $program = app(OrganisationContext::class)->run(
        $organisation,
        fn (): Program => Program::factory()->for($organisation)->create(['name' => 'Before', 'slug' => 'before']),
    );
    app(OrganisationContext::class)->run($organisation, fn () => $membership->programs()->attach($program));

    $this->actingAs($user)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => 'After',
            'slug' => 'after',
        ])
        ->assertOk();

    app(OrganisationContext::class)->run($organisation, function () use ($organisation, $user, $program): void {
        $event = TenantAuditEvent::query()->sole();

        expect($event->organisation_id)->toBe($organisation->id)
            ->and($event->actor_user_id)->toBe($user->id)
            ->and($event->type)->toBe(TenantAuditEventType::ProgramUpdated)
            ->and($event->schema_version)->toBe(1)
            ->and($event->payload)->toBe([
                'program_id' => $program->id,
                'changed_fields' => ['name', 'slug'],
            ])
            ->and(json_encode($event->payload, JSON_THROW_ON_ERROR))->not->toContain('Before')->not->toContain('After');
    });
});

it('rolls back a protected mutation when its required tenant audit cannot be recorded', function () {
    $user = User::factory()->create();
    $organisation = Organisation::factory()->active()->create();
    $organisation->memberships()->create([
        'user_id' => $user->id,
        'role' => OrganisationRole::OrganisationAdministrator,
    ]);
    $program = app(OrganisationContext::class)->run(
        $organisation,
        fn (): Program => Program::factory()->for($organisation)->create(['name' => 'Before', 'slug' => 'before']),
    );
    $this->mock(RecordTenantAuditEvent::class, function ($mock): void {
        $mock->shouldReceive('handle')->once()->andThrow(new RuntimeException('Audit unavailable.'));
    });

    $this->actingAs($user)
        ->patchJson(route('organisations.programs.update', [$organisation, $program]), [
            'name' => 'After',
            'slug' => 'after',
        ])
        ->assertServerError();

    expect(DB::table('programs')->where('id', $program->id)->value('name'))->toBe('Before')
        ->and(DB::table('tenant_audit_events')->count())->toBe(0);
});

it('rejects non-allowlisted payload fields and prevents audit mutation at application and database boundaries', function () {
    $organisation = Organisation::factory()->active()->create();
    $user = User::factory()->create();

    app(OrganisationContext::class)->run($organisation, function () use ($organisation, $user): void {
        expect(fn () => app(RecordTenantAuditEvent::class)->handle(
            $organisation,
            TenantAuditEventType::ProgramUpdated,
            'program',
            '1',
            ['program_id' => 1, 'changed_fields' => ['name'], 'name' => 'secret'],
            $user,
        ))->toThrow(InvalidArgumentException::class, 'allowlist');

        $event = app(RecordTenantAuditEvent::class)->handle(
            $organisation,
            TenantAuditEventType::ProgramUpdated,
            'program',
            '1',
            ['program_id' => 1, 'changed_fields' => ['name']],
            $user,
        );

        expect(fn () => $event->update(['subject_id' => '2']))
            ->toThrow(LogicException::class, 'append-only');
        expect(fn () => DB::transaction(
            fn () => DB::table('tenant_audit_events')->where('id', $event->id)->delete(),
        ))->toThrow(QueryException::class, 'append-only');
    });
});

it('scopes tenant audits to the current Organisation', function () {
    $first = Organisation::factory()->active()->create();
    $second = Organisation::factory()->active()->create();

    app(OrganisationContext::class)->run($first, fn () => TenantAuditEvent::factory()->for($first)->create());
    app(OrganisationContext::class)->run($second, fn () => TenantAuditEvent::factory()->for($second)->create());

    app(OrganisationContext::class)->run($first, function () use ($first): void {
        expect(TenantAuditEvent::query()->count())->toBe(1)
            ->and(TenantAuditEvent::query()->sole()->organisation_id)->toBe($first->id);
    });
});
