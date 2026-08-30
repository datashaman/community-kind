<?php

use App\Actions\Organisations\TransitionOrganisationStatus;
use App\Enums\OrganisationLifecycleEventType;
use App\Enums\OrganisationStatus;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

it('supports every approved organisation lifecycle transition', function (OrganisationStatus $from, OrganisationStatus $to) {
    $organisation = Organisation::factory()->create([
        'status' => $from,
        'deletion_scheduled_for' => $from === OrganisationStatus::ScheduledForDeletion ? now()->subSecond() : null,
    ]);

    app(TransitionOrganisationStatus::class)->handle($organisation, $to);

    $persisted = Organisation::withTrashed()->findOrFail($organisation->id);

    expect($persisted->status)->toBe($to)
        ->and($persisted->lifecycleEvents()->latest('occurred_at')->first()->type)
        ->toBe(OrganisationLifecycleEventType::StatusChanged);
})->with([
    'pending to active' => [OrganisationStatus::Pending, OrganisationStatus::Active],
    'pending to scheduled deletion' => [OrganisationStatus::Pending, OrganisationStatus::ScheduledForDeletion],
    'active to archived' => [OrganisationStatus::Active, OrganisationStatus::Archived],
    'archived to active' => [OrganisationStatus::Archived, OrganisationStatus::Active],
    'archived to scheduled deletion' => [OrganisationStatus::Archived, OrganisationStatus::ScheduledForDeletion],
    'scheduled deletion to archived' => [OrganisationStatus::ScheduledForDeletion, OrganisationStatus::Archived],
    'scheduled deletion to deleted' => [OrganisationStatus::ScheduledForDeletion, OrganisationStatus::Deleted],
]);

it('rejects every lifecycle transition outside the approved matrix', function (OrganisationStatus $from, OrganisationStatus $to) {
    $organisation = Organisation::factory()->create([
        'status' => $from,
        'deletion_scheduled_for' => $from === OrganisationStatus::ScheduledForDeletion ? now()->subSecond() : null,
    ]);

    expect(fn () => app(TransitionOrganisationStatus::class)->handle(
        $organisation,
        $to,
    ))->toThrow(ValidationException::class);

    expect($organisation->fresh()->status)->toBe($from);
})->with((function (): array {
    $transitions = [];

    foreach (OrganisationStatus::cases() as $from) {
        foreach (OrganisationStatus::cases() as $to) {
            if (! in_array($to, $from->allowedTransitions(), true)) {
                $transitions["{$from->value} to {$to->value}"] = [$from, $to];
            }
        }
    }

    return $transitions;
})());

it('schedules deletion with a 30 day recovery period before purging', function () {
    $this->travelTo(now()->startOfSecond());

    $owner = User::factory()->create();
    $organisation = $owner->currentOrganisation;
    app(TransitionOrganisationStatus::class)->handle($organisation, OrganisationStatus::Archived, $owner);

    $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->delete(route('organisations.destroy', $organisation), ['name' => $organisation->name])
        ->assertRedirect(route('organisations.edit', $organisation));

    expect($organisation->fresh()->status)->toBe(OrganisationStatus::ScheduledForDeletion)
        ->and($organisation->fresh()->deletion_scheduled_for->equalTo(now()->addDays(30)))->toBeTrue()
        ->and($owner->fresh()->current_organisation_id)->toBeNull();

    $this->artisan('organisations:purge-scheduled')->assertSuccessful();
    $this->assertNotSoftDeleted($organisation);

    $this->travel(30)->days();
    $this->artisan('organisations:purge-scheduled')->assertSuccessful();

    $this->assertSoftDeleted($organisation);
    expect(Organisation::withTrashed()->findOrFail($organisation->id)->status)->toBe(OrganisationStatus::Deleted)
        ->and($owner->fresh()->current_organisation_id)->toBeNull();
    $this->assertDatabaseHas('organisation_slugs', [
        'organisation_id' => $organisation->id,
        'slug' => $organisation->slug,
    ]);
});

it('lets an owner recover a scheduled organisation before the deadline', function () {
    $owner = User::factory()->create();
    $organisation = $owner->currentOrganisation;
    app(TransitionOrganisationStatus::class)->handle($organisation, OrganisationStatus::Archived, $owner);
    app(TransitionOrganisationStatus::class)->handle($organisation, OrganisationStatus::ScheduledForDeletion, $owner);

    $this
        ->actingAs($owner)
        ->withSession(['auth.password_confirmed_at' => now()->timestamp])
        ->patch(route('organisations.lifecycle.update', $organisation), [
            'status' => OrganisationStatus::Archived->value,
        ])
        ->assertRedirect(route('organisations.edit', $organisation));

    expect($organisation->fresh()->status)->toBe(OrganisationStatus::Archived)
        ->and($organisation->fresh()->deletion_scheduled_for)->toBeNull();
});

it('blocks ordinary work while an organisation is not active', function (OrganisationStatus $status) {
    $user = User::factory()->create();
    $organisation = $user->currentOrganisation;
    $organisation->update(['status' => $status]);

    $this
        ->actingAs($user)
        ->get(route('dashboard', $organisation))
        ->assertForbidden();

    $editResponse = $this
        ->actingAs($user)
        ->get(route('organisations.edit', $organisation));

    $editResponse->assertOk();

    if ($status !== OrganisationStatus::Pending) {
        $editResponse->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canUpdateOrganisation', false)
            ->where('permissions.canTransitionOrganisation', true));
    }
})->with([
    OrganisationStatus::Pending,
    OrganisationStatus::Archived,
    OrganisationStatus::ScheduledForDeletion,
]);

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
