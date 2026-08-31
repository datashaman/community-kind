<?php

use App\Actions\Demo\BuildOrganisationScenario;
use App\Actions\Programs\BuildProgramReport;
use App\Actions\Programs\SearchPrograms;
use App\Data\Demo\ScenarioCatalog;
use App\Enums\DonationPaymentStatus;
use App\Enums\OrganisationStatus;
use App\Models\Donation;
use App\Models\DonationFund;
use App\Models\DonationPayment;
use App\Models\DonationReceipt;
use App\Models\FundraisingCampaign;
use App\Models\Membership;
use App\Models\MetricEvent;
use App\Models\Organisation;
use App\Models\Party;
use App\Models\PartyContactPoint;
use App\Models\Program;
use App\Models\RoleAssignment;
use App\Models\ServiceCase;
use App\Models\User;
use App\OrganisationCache;
use App\OrganisationContext;
use App\OrganisationStorage;
use Database\Seeders\CommunityKindScenarioSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $key = 'base64:'.base64_encode(str_repeat('d', 32));

    config([
        'classified_data.encryption.current_version' => 'scenario-data-v1',
        'classified_data.encryption.keys' => ['scenario-data-v1' => $key],
        'classified_data.contact_index.current_version' => 'scenario-index-v1',
        'classified_data.contact_index.previous_version' => null,
        'classified_data.contact_index.keys' => ['scenario-index-v1' => $key],
        'cache.default' => 'array',
    ]);
});

it('seeds the versioned synthetic scenarios deterministically', function () {
    $this->seed(CommunityKindScenarioSeeder::class);

    $contactIds = PartyContactPoint::withoutGlobalScopes()->orderBy('id')->pluck('id')->all();
    $partyIds = Party::withoutGlobalScopes()->orderBy('uuid')->pluck('id', 'uuid')->all();
    $harbourKindId = Organisation::query()->where('slug', 'harbourkind')->valueOrFail('id');
    $neighbourLinkId = Organisation::query()->where('slug', 'neighbourlink')->valueOrFail('id');
    $showcaseCase = ServiceCase::withoutGlobalScopes()->firstOrFail();
    $serviceMetric = MetricEvent::withoutGlobalScopes()->where('code', 'service_delivered')->firstOrFail();
    $harbourKindPartyCounts = DB::table('parties')
        ->where('organisation_id', $harbourKindId)
        ->selectRaw('kind, count(*) as aggregate')
        ->groupBy('kind')
        ->pluck('aggregate', 'kind')
        ->map(fn (int|string $count): int => (int) $count)
        ->all();
    $neighbourLinkPartyCounts = DB::table('parties')
        ->where('organisation_id', $neighbourLinkId)
        ->selectRaw('kind, count(*) as aggregate')
        ->groupBy('kind')
        ->pluck('aggregate', 'kind')
        ->map(fn (int|string $count): int => (int) $count)
        ->all();

    expect(ScenarioCatalog::VERSION)->toBe('2026.4')
        ->and(ScenarioCatalog::AS_OF)->toBe('2026-06-30 23:59:59')
        ->and(Date::getTestNow())->toBeNull()
        ->and(Organisation::query()->count())->toBe(2)
        ->and(User::query()->count())->toBe(11)
        ->and(Membership::withoutGlobalScopes()->count())->toBe(12)
        ->and(Party::withoutGlobalScopes()->count())->toBe(2030)
        ->and(PartyContactPoint::withoutGlobalScopes()->count())->toBe(32)
        ->and(Program::withoutGlobalScopes()->count())->toBe(4)
        ->and(RoleAssignment::withoutGlobalScopes()->count())->toBe(10)
        ->and(DB::table('membership_program')->count())->toBe(6)
        ->and(ServiceCase::withoutGlobalScopes()->count())->toBe(1)
        ->and(MetricEvent::withoutGlobalScopes()->count())->toBe(5)
        ->and(FundraisingCampaign::withoutGlobalScopes()->count())->toBe(1)
        ->and(DonationFund::withoutGlobalScopes()->count())->toBe(1)
        ->and(Donation::withoutGlobalScopes()->count())->toBe(1)
        ->and(DonationPayment::withoutGlobalScopes()->count())->toBe(1)
        ->and(DonationPayment::withoutGlobalScopes()->sole()->status)->toBe(DonationPaymentStatus::Succeeded)
        ->and(DonationReceipt::withoutGlobalScopes()->sole()->marker)->toBe('Demo—Not a tax receipt')
        ->and($showcaseCase->opened_at->toDateTimeString())->toBe('2026-06-02 06:00:00')
        ->and($showcaseCase->closed_at?->toDateTimeString())->toBe('2026-06-28 13:00:00')
        ->and($serviceMetric->occurred_at->toDateTimeString())->toBe('2026-06-10 10:00:00')
        ->and(Membership::withoutGlobalScopes()->where('organisation_id', $harbourKindId)->count())->toBe(9)
        ->and(Membership::withoutGlobalScopes()->where('organisation_id', $neighbourLinkId)->count())->toBe(3)
        ->and($harbourKindPartyCounts)->toBe(['household' => 100, 'organisation' => 150, 'person' => 1750])
        ->and($neighbourLinkPartyCounts)->toBe(['person' => 30])
        ->and(Organisation::query()->where('slug', 'harbourkind')->value('uuid'))->toBe('10000000-0000-4000-8000-000000000001')
        ->and(Organisation::query()->where('slug', 'neighbourlink')->value('uuid'))->toBe('20000000-0000-4000-8000-000000000001')
        ->and(User::query()->where('email', 'switcher@community-kind.example.test')->value('name'))->toBe('CommunityKind Demo Organisation Switcher');

    $this->seed(CommunityKindScenarioSeeder::class);

    expect(Organisation::query()->count())->toBe(2)
        ->and(User::query()->count())->toBe(11)
        ->and(Membership::withoutGlobalScopes()->count())->toBe(12)
        ->and(Party::withoutGlobalScopes()->count())->toBe(2030)
        ->and(PartyContactPoint::withoutGlobalScopes()->orderBy('id')->pluck('id')->all())->toBe($contactIds)
        ->and(Party::withoutGlobalScopes()->orderBy('uuid')->pluck('id', 'uuid')->all())->toBe($partyIds)
        ->and(ServiceCase::withoutGlobalScopes()->count())->toBe(1)
        ->and(MetricEvent::withoutGlobalScopes()->count())->toBe(5)
        ->and(FundraisingCampaign::withoutGlobalScopes()->count())->toBe(1)
        ->and(DonationFund::withoutGlobalScopes()->count())->toBe(1)
        ->and(Donation::withoutGlobalScopes()->count())->toBe(1)
        ->and(DonationPayment::withoutGlobalScopes()->count())->toBe(1)
        ->and(DonationReceipt::withoutGlobalScopes()->count())->toBe(1);
});

it('keeps every scenario identity and reserved showcase explicitly synthetic', function () {
    $organisations = ScenarioCatalog::organisations();
    $identities = collect($organisations)->flatMap(fn (array $organisation): array => [
        ...$organisation['members'],
        ...$organisation['parties'],
    ]);

    expect(collect($organisations)->every(fn (array $organisation): bool => $organisation['synthetic']))->toBeTrue()
        ->and($organisations[0]['timezone'])->toBe('Africa/Johannesburg')
        ->and($organisations[0]['currency'])->toBe('ZAR')
        ->and($organisations[0]['reporting_at'])->toBe('2026-06-30T23:59:59+02:00')
        ->and(array_sum($organisations[0]['party_population']))->toBe(2000)
        ->and($organisations[1]['timezone'])->toBe('Europe/London')
        ->and($organisations[1]['currency'])->toBe('GBP')
        ->and($organisations[1]['reporting_at'])->toBe('2026-06-30T23:59:59+01:00')
        ->and(array_sum($organisations[1]['party_population']))->toBe(30)
        ->and(ScenarioCatalog::showcases())->toHaveCount(4)
        ->and(collect(ScenarioCatalog::showcases())->pluck('uuid')->unique())->toHaveCount(4)
        ->and(collect(ScenarioCatalog::showcases())->every(fn (array $showcase): bool => $showcase['synthetic']))->toBeTrue()
        ->and($identities->whereNotNull('email')->every(fn (array $identity): bool => str_ends_with($identity['email'], '.example.test')))->toBeTrue()
        ->and($identities->whereNotNull('telephone')->every(fn (array $identity): bool => str_starts_with($identity['telephone'], '+1 202-555-01')))->toBeTrue();
});

it('reconciles tenant relationships and isolates search files cache and aggregates', function () {
    Storage::fake();
    Cache::flush();
    $this->seed(CommunityKindScenarioSeeder::class);

    $harbourKind = Organisation::query()->where('slug', 'harbourkind')->firstOrFail();
    $neighbourLink = Organisation::query()->where('slug', 'neighbourlink')->firstOrFail();
    $context = app(OrganisationContext::class);
    $storage = app(OrganisationStorage::class);
    $cache = app(OrganisationCache::class);

    $harbour = $context->run($harbourKind, function () use ($storage, $cache): array {
        $path = $storage->put('scenario-proof.txt', 'HarbourKind synthetic proof');
        $report = app(BuildProgramReport::class)->handle();

        return [
            'report' => $report,
            'other_search_count' => app(SearchPrograms::class)->handle('Neighbour Support Network')->count(),
            'path' => $path,
            'cache_key' => $cache->key('scenario-proof'),
            'cached' => $cache->remember('scenario-proof', 60, fn (): string => 'HarbourKind synthetic cache'),
        ];
    });
    $neighbour = $context->run($neighbourLink, function () use ($storage, $cache): array {
        $path = $storage->put('scenario-proof.txt', 'NeighbourLink synthetic proof');
        $report = app(BuildProgramReport::class)->handle();

        return [
            'report' => $report,
            'other_search_count' => app(SearchPrograms::class)->handle('Housing and Homelessness Support')->count(),
            'path' => $path,
            'cache_key' => $cache->key('scenario-proof'),
            'cached' => $cache->remember('scenario-proof', 60, fn (): string => 'NeighbourLink synthetic cache'),
        ];
    });

    expect($harbour['report']['program_count'])->toBe(3)
        ->and($neighbour['report']['program_count'])->toBe(1)
        ->and($harbour['report']['program_names'])->not->toContain('Neighbour Support Network')
        ->and($neighbour['report']['program_names'])->not->toContain('Housing and Homelessness Support')
        ->and($harbour['other_search_count'])->toBe(0)
        ->and($neighbour['other_search_count'])->toBe(0)
        ->and($harbour['path'])->not->toBe($neighbour['path'])
        ->and($harbour['cache_key'])->not->toBe($neighbour['cache_key'])
        ->and($harbour['cached'])->toBe('HarbourKind synthetic cache')
        ->and($neighbour['cached'])->toBe('NeighbourLink synthetic cache')
        ->and(Storage::get($harbour['path']))->toBe('HarbourKind synthetic proof')
        ->and(Storage::get($neighbour['path']))->toBe('NeighbourLink synthetic proof')
        ->and(DB::table('organisation_members as memberships')
            ->join('parties', 'parties.id', '=', 'memberships.person_party_id')
            ->whereColumn('memberships.organisation_id', '!=', 'parties.organisation_id')
            ->count())->toBe(0)
        ->and(DB::table('role_assignments as roles')
            ->join('organisation_members as memberships', 'memberships.id', '=', 'roles.membership_id')
            ->whereColumn('roles.organisation_id', '!=', 'memberships.organisation_id')
            ->count())->toBe(0)
        ->and(DB::table('membership_program as access')
            ->join('programs', 'programs.id', '=', 'access.program_id')
            ->whereColumn('access.organisation_id', '!=', 'programs.organisation_id')
            ->count())->toBe(0);
});

it('stores scenario contacts as classified ciphertext', function () {
    $this->seed(CommunityKindScenarioSeeder::class);

    $ciphertexts = DB::table('party_contact_points')->pluck('encrypted_value');

    expect($ciphertexts)->toHaveCount(32)
        ->and($ciphertexts->every(fn (string $ciphertext): bool => ! str_contains($ciphertext, '@') && ! str_contains($ciphertext, '555')))->toBeTrue();
});

it('refuses to install the synthetic scenarios in production', function () {
    $environment = app()->environment();
    app()->detectEnvironment(fn (): string => 'production');

    try {
        expect(fn () => app(CommunityKindScenarioSeeder::class)->run(app(BuildOrganisationScenario::class)))
            ->toThrow(LogicException::class, 'Synthetic demo scenarios cannot be seeded in production.');
    } finally {
        app()->detectEnvironment(fn (): string => $environment);
    }
});

it('does not reactivate an archived scenario organisation while reseeding', function () {
    Organisation::factory()->create([
        'uuid' => '10000000-0000-4000-8000-000000000001',
        'name' => 'HarbourKind',
        'slug' => 'harbourkind',
        'status' => OrganisationStatus::Archived,
        'access_version' => 4,
    ]);

    expect(fn () => $this->seed(CommunityKindScenarioSeeder::class))
        ->toThrow(LogicException::class, 'The HarbourKind demo Organisation is not active and cannot be reseeded safely.');

    expect(Organisation::query()->where('slug', 'harbourkind')->firstOrFail())
        ->status->toBe(OrganisationStatus::Archived)
        ->access_version->toBe(4);
});
