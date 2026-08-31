<?php

use App\Enums\OrganisationAccessLevel;
use App\Enums\OrganisationAccessScope;
use App\Enums\OrganisationStatus;
use App\Models\Organisation;
use App\Models\OrganisationAccessHold;
use App\Models\OrganisationSlug;
use App\Models\User;

it('renders only the Organisation resolved from the exact public host', function () {
    Organisation::factory()->active()->create([
        'name' => 'HarbourKind',
        'slug' => 'harbourkind',
    ]);
    $neighbourLink = Organisation::factory()->active()->create([
        'name' => 'NeighbourLink',
        'slug' => 'neighbourlink',
    ]);
    $staffUser = User::factory()->create();
    $neighbourLink->memberships()->create([
        'user_id' => $staffUser->id,
    ]);
    $staffUser->switchOrganisation($neighbourLink);

    $response = $this
        ->actingAs($staffUser)
        ->get('https://harbourkind.community-kind.test/?organisation=neighbourlink');

    $response
        ->assertOk()
        ->assertSee('HarbourKind')
        ->assertDontSee('NeighbourLink')
        ->assertSee('<link rel="canonical" href="https://harbourkind.community-kind.test">', false);
});

it('provisions unique DNS-length slugs for long Organisation names', function () {
    $name = str_repeat('Long Community Name ', 5);
    $firstOrganisation = Organisation::factory()->active()->create([
        'name' => $name,
        'slug' => null,
    ]);
    $secondOrganisation = Organisation::factory()->active()->create([
        'name' => $name,
        'slug' => null,
    ]);

    $response = $this->get("https://{$secondOrganisation->slug}.community-kind.test/");

    $response->assertSee($name);
    expect(strlen($firstOrganisation->slug))->toBeLessThanOrEqual(63)
        ->and(strlen($secondOrganisation->slug))->toBeLessThanOrEqual(63)
        ->and($secondOrganisation->slug)->not->toBe($firstOrganisation->slug);
});

it('does not serve staff routes from a tenant public host', function () {
    Organisation::factory()->active()->create([
        'name' => 'HarbourKind',
        'slug' => 'harbourkind',
    ]);

    $response = $this->get('https://harbourkind.community-kind.test/login');

    $response->assertNotFound();
});

it('does not serve public discovery from an unknown tenant host', function () {
    $response = $this->get('https://unknown.community-kind.test/.well-known/security.txt');

    $response->assertNotFound();
});

it('does not serve application routes from an unverified custom domain', function () {
    $response = $this->get('https://community.example.org/');

    $response->assertNotFound();
});

it('rejects an unverified host before a previous staff slug can reveal an Organisation', function () {
    $organisation = Organisation::factory()->active()->create([
        'name' => 'HarbourKind',
        'slug' => 'harbourkind',
    ]);
    OrganisationSlug::factory()->for($organisation)->create([
        'slug' => 'old-harbourkind',
        'redirect_until' => now()->addDay(),
    ]);

    $response = $this->get('https://community.example.org/settings/organisations/old-harbourkind');

    $response->assertNotFound();
});

it('escapes the Organisation name on its public home', function () {
    Organisation::factory()->active()->create([
        'name' => 'HarbourKind <script>alert("tenant")</script>',
        'slug' => 'harbourkind',
    ]);

    $response = $this->get('https://harbourkind.community-kind.test/');

    $response
        ->assertOk()
        ->assertSee('HarbourKind &lt;script&gt;alert(&quot;tenant&quot;)&lt;/script&gt;', false)
        ->assertDontSee('<script>alert("tenant")</script>', false);
});

it('returns a neutral 404 for Organisations that are not active', function (OrganisationStatus $status) {
    Organisation::factory()->create([
        'name' => 'Private Lifecycle Organisation',
        'slug' => 'private-lifecycle',
        'status' => $status,
        'deletion_scheduled_for' => $status === OrganisationStatus::ScheduledForDeletion
            ? now()->addMonth()
            : null,
    ]);

    $response = $this->get('https://private-lifecycle.community-kind.test/');

    $response
        ->assertNotFound()
        ->assertDontSee('Private Lifecycle Organisation');
})->with([
    'pending' => OrganisationStatus::Pending,
    'archived' => OrganisationStatus::Archived,
    'scheduled for deletion' => OrganisationStatus::ScheduledForDeletion,
    'deleted' => OrganisationStatus::Deleted,
]);

it('returns a neutral 404 for a soft-deleted Organisation', function () {
    Organisation::factory()->active()->trashed()->create([
        'name' => 'Deleted HarbourKind',
        'slug' => 'deleted-harbourkind',
    ]);

    $response = $this->get('https://deleted-harbourkind.community-kind.test/');

    $response
        ->assertNotFound()
        ->assertDontSee('Deleted HarbourKind');
});

it('returns a neutral 404 while an applicable public Access Hold restricts the Organisation', function () {
    $organisation = Organisation::factory()->active()->create([
        'name' => 'Held HarbourKind',
        'slug' => 'held-harbourkind',
    ]);
    OrganisationAccessHold::factory()->for($organisation)->create([
        'scope' => OrganisationAccessScope::Public,
        'access_level' => OrganisationAccessLevel::ReadOnly,
    ]);

    $response = $this->get('https://held-harbourkind.community-kind.test/');

    $response
        ->assertNotFound()
        ->assertDontSee('Held HarbourKind');
});

it('does not apply a staff-only Access Hold to the public host', function () {
    $organisation = Organisation::factory()->active()->create([
        'name' => 'Public HarbourKind',
        'slug' => 'public-harbourkind',
    ]);
    OrganisationAccessHold::factory()->for($organisation)->create([
        'scope' => OrganisationAccessScope::Staff,
        'access_level' => OrganisationAccessLevel::Denied,
    ]);

    $response = $this->get('https://public-harbourkind.community-kind.test/');

    $response->assertSee('Public HarbourKind');
});

it('returns the same neutral response for unknown reserved and malformed public hosts', function (string $host) {
    Organisation::factory()->active()->create([
        'name' => 'Reserved Platform Organisation',
        'slug' => 'app',
    ]);

    $unknownResponse = $this->get('https://unknown.community-kind.test/');
    $candidateResponse = $this->get("https://{$host}/");

    $unknownResponse->assertNotFound();
    $candidateResponse->assertNotFound();
    expect($candidateResponse->getContent())->toBe($unknownResponse->getContent());
})->with([
    'reserved subdomain' => 'app.community-kind.test',
    'nested subdomain' => 'nested.harbourkind.community-kind.test',
    'lookalike parent domain' => 'harbourkind.evil-community-kind.test',
    'appended parent domain' => 'harbourkind.community-kind.test.evil.test',
]);

it('redirects an active previous slug to the canonical public host while preserving the query', function () {
    $organisation = Organisation::factory()->active()->create([
        'name' => 'HarbourKind',
        'slug' => 'harbourkind',
    ]);
    OrganisationSlug::factory()->for($organisation)->create([
        'slug' => 'old-harbourkind',
        'redirect_until' => now()->addDay(),
    ]);

    $response = $this->get('https://old-harbourkind.community-kind.test/?campaign=spring');

    $response->assertRedirect('https://harbourkind.community-kind.test?campaign=spring');
});

it('preserves the public route when redirecting a previous slug', function () {
    $organisation = Organisation::factory()->active()->create([
        'name' => 'HarbourKind',
        'slug' => 'harbourkind',
    ]);
    OrganisationSlug::factory()->for($organisation)->create([
        'slug' => 'old-harbourkind',
        'redirect_until' => now()->addDay(),
    ]);

    $response = $this->get('https://old-harbourkind.community-kind.test/.well-known/security.txt');

    $response->assertRedirect('https://harbourkind.community-kind.test/.well-known/security.txt');
});

it('returns a neutral 404 after a previous public slug redirect expires', function () {
    $organisation = Organisation::factory()->active()->create([
        'name' => 'HarbourKind',
        'slug' => 'harbourkind',
    ]);
    OrganisationSlug::factory()->for($organisation)->create([
        'slug' => 'expired-harbourkind',
        'redirect_until' => now()->subSecond(),
    ]);

    $response = $this->get('https://expired-harbourkind.community-kind.test/');

    $response
        ->assertNotFound()
        ->assertDontSee('HarbourKind');
});
