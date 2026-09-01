<?php

use App\Actions\Demo\ExpireSandboxPair;
use App\Actions\Demo\ProvisionSandboxPair;
use App\Actions\Demo\PurgeSandboxPair;
use App\Enums\OrganisationRole;
use App\Enums\OrganisationStatus;
use App\Enums\SandboxPairStatus;
use App\Enums\TenantAuditEventType;
use App\Exceptions\ClassifiedDataUnavailable;
use App\Models\Membership;
use App\Models\Organisation;
use App\Models\Program;
use App\Models\SandboxBootstrapToken;
use App\Models\SandboxPair;
use App\Models\TenantAuditEvent;
use App\Models\User;
use App\OrganisationContext;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    config(['demo_sandbox.enabled' => true]);
});

it('provisions a confined pair through a single-use hashed bootstrap and audited persona', function () {
    $result = app(ProvisionSandboxPair::class)->handle(12);
    $pair = $result['pair']->refresh();
    $plainTextToken = $result['token'];
    $bootstrap = SandboxBootstrapToken::query()->sole();

    expect($pair->status)->toBe(SandboxPairStatus::Ready)
        ->and($pair->expires_at->diffInHours(now()))->toBeLessThanOrEqual(12)
        ->and($pair->organisations)->toHaveCount(2)
        ->and($pair->organisations->every(fn (Organisation $organisation): bool => $organisation->is_synthetic))->toBeTrue()
        ->and($bootstrap->token_hash)->toBe(hash('sha256', $plainTextToken))
        ->and($bootstrap->getAttributes())->not->toContain($plainTextToken);

    $this->get(route('demo.bootstrap', ['token' => $plainTextToken]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('demo/bootstrap')
            ->where('token', $plainTextToken))
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($bootstrap->refresh()->used_at)->toBeNull()
        ->and($pair->refresh()->status)->toBe(SandboxPairStatus::Ready);

    $this->get(route('demo.bootstrap', ['token' => $plainTextToken]))->assertOk();
    expect($bootstrap->refresh()->used_at)->toBeNull();

    $this->post(route('demo.bootstrap.store', ['token' => $plainTextToken]))
        ->assertRedirect(route('demo.personas.index'))
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($bootstrap->refresh()->used_at)->not->toBeNull()
        ->and($pair->refresh()->status)->toBe(SandboxPairStatus::Active);

    $this->artisan('demo:sandbox:token', ['pair' => $pair->id])
        ->expectsOutputToContain('/demo/bootstrap/')
        ->assertSuccessful();
    $this->artisan('demo:sandbox:token', ['pair' => $pair->id])->assertSuccessful();
    expect($pair->bootstrapTokens()->count())->toBe(3)
        ->and($pair->bootstrapTokens()->whereNull('used_at')->whereNull('revoked_at')->count())->toBe(1);

    $this->get(route('demo.bootstrap', ['token' => $plainTextToken]))
        ->assertRedirect(route('demo.personas.index'));
    $this->get(route('demo.personas.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('demo/personas')
            ->has('personas', 5));

    $harbourKind = $pair->organisations()->where('sandbox_template', 'harbourkind')->firstOrFail();
    $administrator = Membership::query()->findOrFail((int) DB::table('role_assignments')
        ->where('organisation_id', $harbourKind->id)
        ->whereNull('program_id')
        ->where('role', OrganisationRole::OrganisationAdministrator)
        ->value('membership_id'));

    $this->post(route('demo.personas.store'), ['membership_id' => $administrator->id])
        ->assertRedirect(route('dashboard', ['current_organisation' => $harbourKind]))
        ->assertSessionHas('auth.password_confirmed_at');
    $this->assertAuthenticatedAs($administrator->user);

    expect(TenantAuditEvent::withoutGlobalScopes()->where('type', TenantAuditEventType::DemoPersonaSelected)->sole()->payload)
        ->toMatchArray(['membership_id' => $administrator->id, 'generation' => 1]);

    $unrelated = Organisation::factory()->active()->create();
    $unrelated->members()->attach($administrator->user, ['role' => OrganisationRole::OrganisationAdministrator]);
    $this->get(route('dashboard', ['current_organisation' => $unrelated]))->assertNotFound();
    $this->post(route('organisations.invitations.store', ['organisation' => $harbourKind]))->assertForbidden();
    $this->patch(route('profile.update'), ['name' => 'Evaluator', 'email' => 'evaluator@example.com'])->assertForbidden();
    $bootstrapUrl = route('demo.bootstrap', ['token' => $plainTextToken]);
    $this->get("https://{$harbourKind->slug}.community-kind.test/")->assertOk();
    $this->get("https://{$unrelated->slug}.community-kind.test/")->assertNotFound();

    $this->flushSession();
    $this->get($bootstrapUrl)->assertGone();
});

it('resets only the selected Organisation and invalidates its generation', function () {
    $result = app(ProvisionSandboxPair::class)->handle();
    $pair = $result['pair'];
    $this->get(route('demo.bootstrap', ['token' => $result['token']]))->assertOk();
    $this->post(route('demo.bootstrap.store', ['token' => $result['token']]))->assertRedirect();
    $harbourKind = $pair->organisations()->where('sandbox_template', 'harbourkind')->firstOrFail();
    $neighbourLink = $pair->organisations()->where('sandbox_template', 'neighbourlink')->firstOrFail();
    $administrator = Membership::query()->findOrFail((int) DB::table('role_assignments')
        ->where('organisation_id', $harbourKind->id)
        ->whereNull('program_id')
        ->where('role', OrganisationRole::OrganisationAdministrator)
        ->value('membership_id'));
    $caseWorker = Membership::query()->findOrFail((int) DB::table('role_assignments')
        ->where('organisation_id', $harbourKind->id)
        ->where('role', OrganisationRole::CaseWorker)
        ->orderBy('id')
        ->value('membership_id'));
    $this->post(route('demo.personas.store'), ['membership_id' => $caseWorker->id])->assertRedirect();
    $this->post(route('demo.organisations.reset', ['organisation' => $harbourKind]), [
        'slug' => $harbourKind->slug,
    ])->assertForbidden();
    $this->post(route('demo.personas.store'), ['membership_id' => $administrator->id])->assertRedirect();
    $unrelated = Organisation::factory()->active()->create();
    app(OrganisationContext::class)->run(
        $neighbourLink,
        fn () => Program::factory()->create(['organisation_id' => $neighbourLink->id]),
    );

    $response = $this->post(route('demo.organisations.reset', ['organisation' => $harbourKind]), [
        'slug' => $harbourKind->slug,
    ]);

    $replacement = $pair->organisations()->where('sandbox_template', 'harbourkind')->firstOrFail();
    $response->assertRedirectContains('/demo/bootstrap/');
    expect($replacement->id)->not->toBe($harbourKind->id)
        ->and($replacement->slug)->toBe($harbourKind->slug)
        ->and($replacement->demo_generation)->toBe(2)
        ->and($harbourKind->refresh()->sandbox_pair_id)->toBe($pair->id)
        ->and($harbourKind->status)->toBe(OrganisationStatus::Deleted)
        ->and($harbourKind->trashed())->toBeTrue()
        ->and($pair->organisations()->count())->toBe(2)
        ->and($pair->organisations()->withTrashed()->count())->toBe(3)
        ->and($pair->organisations()->whereKey($neighbourLink->id)->exists())->toBeTrue()
        ->and(DB::table('programs')->where('organisation_id', $neighbourLink->id)->whereNull('deleted_at')->count())->toBe(2)
        ->and(Organisation::query()->whereKey($unrelated->id)->exists())->toBeTrue()
        ->and(TenantAuditEvent::withoutGlobalScopes()->where('type', TenantAuditEventType::DemoOrganisationReset)->count())->toBe(1);

    app(ExpireSandboxPair::class)->handle($pair->refresh());
    app(PurgeSandboxPair::class)->handle($pair->refresh());

    expect(User::query()->findOrFail($administrator->user_id)->email)->toStartWith('purged-sandbox-')
        ->and($pair->refresh()->status)->toBe(SandboxPairStatus::Purged);
});

it('expires sessions and idempotently purges only synthetic sandbox identities', function () {
    $pair = SandboxPair::factory()->create([
        'status' => SandboxPairStatus::Active,
        'expires_at' => now()->subMinute(),
    ]);
    $organisation = Organisation::factory()->active()->create([
        'sandbox_pair_id' => $pair->id,
        'sandbox_template' => 'harbourkind',
        'demo_generation' => 1,
        'is_synthetic' => true,
    ]);
    $user = User::query()->create([
        'name' => 'Synthetic Sandbox Administrator',
        'email' => 'administrator+sandbox@example.test',
        'password' => 'password',
        'current_organisation_id' => $organisation->id,
        'email_verified_at' => now(),
    ]);
    $organisation->members()->attach($user, ['role' => OrganisationRole::OrganisationAdministrator]);
    $token = SandboxBootstrapToken::query()->create([
        'sandbox_pair_id' => $pair->id,
        'token_hash' => hash('sha256', 'token'),
        'generation' => 1,
        'expires_at' => now()->subMinute(),
    ]);
    DB::table('sessions')->insert([
        'id' => 'sandbox-session',
        'user_id' => $user->id,
        'payload' => '',
        'last_activity' => now()->timestamp,
    ]);

    app(ExpireSandboxPair::class)->handle($pair);

    expect($pair->refresh()->status)->toBe(SandboxPairStatus::Expired)
        ->and($pair->generation)->toBe(2)
        ->and($organisation->refresh()->status)->toBe(OrganisationStatus::Archived)
        ->and($token->refresh()->revoked_at)->not->toBeNull()
        ->and(DB::table('sessions')->where('id', 'sandbox-session')->exists())->toBeFalse();

    app(PurgeSandboxPair::class)->handle($pair);
    app(PurgeSandboxPair::class)->handle($pair->refresh());

    expect($pair->refresh()->status)->toBe(SandboxPairStatus::Purged)
        ->and($pair->purged_at)->not->toBeNull()
        ->and(Organisation::withTrashed()->findOrFail($organisation->id)->trashed())->toBeTrue()
        ->and(SandboxBootstrapToken::query()->whereKey($token->id)->exists())->toBeFalse()
        ->and(User::query()->findOrFail($user->id)->email)->toStartWith('purged-sandbox-')
        ->and($user->refresh()->current_organisation_id)->toBeNull();
});

it('does not expose sandbox provisioning outside explicitly enabled environments', function () {
    config(['demo_sandbox.enabled' => false]);

    expect(fn () => app(ProvisionSandboxPair::class)->handle())
        ->toThrow(NotFoundHttpException::class);
});

it('fails closed without exposing a partially provisioned sandbox', function () {
    config(['classified_data.encryption.keys' => []]);

    expect(fn () => app(ProvisionSandboxPair::class)->handle())
        ->toThrow(ClassifiedDataUnavailable::class, 'Classified data is unavailable.');

    $pair = SandboxPair::query()->sole();
    expect($pair->status)->toBe(SandboxPairStatus::Failed)
        ->and($pair->status->isAccessible())->toBeFalse()
        ->and($pair->bootstrapTokens()->count())->toBe(0);
});
