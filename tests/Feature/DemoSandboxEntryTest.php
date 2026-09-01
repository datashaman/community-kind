<?php

use App\Actions\Demo\ProvisionSandboxPair;
use App\Enums\SandboxPairStatus;
use App\Models\SandboxPair;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['demo_sandbox.enabled' => true]);
});

it('presents a public self-service demo entry', function () {
    $this->get('/demo')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('demo/start')
            ->where('lifetimeHours', 24));
});

it('provisions one pending sandbox for repeated submissions from the same session', function () {
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.70']);
    $first = $this->post('/demo');
    $pair = SandboxPair::query()->sole();
    $location = $first->headers->get('Location');

    $first->assertRedirectContains('/demo/bootstrap/')
        ->assertSessionHas('demo_pending_pair_id', $pair->id);
    expect($pair->status)->toBe(SandboxPairStatus::Ready)
        ->and($location)->toBeString();

    $this->post('/demo')->assertRedirect($location);
    $this->post('/demo')->assertRedirect($location);
    $this->post('/demo')->assertTooManyRequests();
    expect(SandboxPair::query()->count())->toBe(1);
});

it('reports provisioning failures without exposing a partial sandbox', function () {
    config(['classified_data.encryption.keys' => []]);

    $this->from('/demo')->post('/demo')
        ->assertRedirect('/demo')
        ->assertSessionHasErrors([
            'demo' => 'The demo could not be prepared. Please try again.',
        ]);

    expect(SandboxPair::query()->sole()->status)->toBe(SandboxPairStatus::Failed);
});

it('expires the current sandbox and prepares a clean replacement', function () {
    $result = app(ProvisionSandboxPair::class)->handle();
    $pair = $result['pair'];
    $oldSlugs = $pair->organisations()->pluck('slug');
    $this->post(route('demo.bootstrap.store', ['token' => $result['token']]))->assertRedirect();
    $this->get('/demo')->assertRedirect(route('demo.personas.index'));

    $this->delete('/demo')
        ->assertRedirectContains('/demo/bootstrap/')
        ->assertSessionHas('demo_pending_pair_id');

    expect($pair->refresh()->status)->toBe(SandboxPairStatus::Expired)
        ->and(SandboxPair::query()->count())->toBe(2);
    $replacementSlugs = SandboxPair::query()->whereKeyNot($pair->id)->sole()->organisations()->pluck('slug');
    expect($replacementSlugs->intersect($oldSlugs))->toBeEmpty();
    $this->assertGuest();
});

it('does not expose self-service provisioning when demo sandboxes are disabled', function () {
    config(['demo_sandbox.enabled' => false]);

    $this->get('/demo')->assertNotFound();
    $this->post('/demo')->assertNotFound();
    $this->delete('/demo')->assertNotFound();
});
