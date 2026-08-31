<?php

use App\Models\Organisation;
use Illuminate\Support\Carbon;

it('publishes host-correct RFC 9116 discovery without tenant content on the central host', function () {
    Organisation::factory()->create(['name' => 'Do not expose this tenant']);

    $this->get('https://localhost/.well-known/security.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Contact: https://github.com/datashaman/community-kind/security/advisories/new', false)
        ->assertSee('Expires: 2027-08-30T00:00:00Z', false)
        ->assertSee('Canonical: https://localhost/.well-known/security.txt', false)
        ->assertSee('Policy: https://localhost/security-policy', false)
        ->assertDontSee('Do not expose this tenant', false);
});

it('publishes host-correct RFC 9116 discovery without tenant content on an active public host', function () {
    Organisation::factory()->create(['name' => 'Do not expose this tenant']);
    Organisation::factory()->active()->create([
        'name' => 'HarbourKind',
        'slug' => 'harbourkind',
    ]);

    $this->get('https://harbourkind.community-kind.test/.well-known/security.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Contact: https://github.com/datashaman/community-kind/security/advisories/new', false)
        ->assertSee('Expires: 2027-08-30T00:00:00Z', false)
        ->assertSee('Canonical: https://harbourkind.community-kind.test/.well-known/security.txt', false)
        ->assertSee('Policy: https://harbourkind.community-kind.test/security-policy', false)
        ->assertDontSee('Do not expose this tenant', false);
});

it('publishes the coordinated vulnerability-reporting policy', function () {
    $this->get('/security-policy')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8')
        ->assertSee('Do not report suspected vulnerabilities in a public issue.', false)
        ->assertSee('business days', false)
        ->assertSee('synthetic data', false);
});

it('keeps the RFC 9116 expiry current and within one year', function () {
    $expires = Carbon::parse((string) config('security.security_txt_expires'));

    expect($expires->isFuture())->toBeTrue()
        ->and(now()->diffInDays($expires))->toBeLessThanOrEqual(365);
});
