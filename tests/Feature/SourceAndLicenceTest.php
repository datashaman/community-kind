<?php

declare(strict_types=1);

it('publishes the corresponding source and licence information', function (): void {
    config()->set('source.repository', 'https://github.com/datashaman/community-kind');
    config()->set('source.release', 'test-release');

    $this->get(route('source-and-licence'))
        ->assertOk()
        ->assertSee('Source and licence')
        ->assertSee('test-release')
        ->assertSee('https://github.com/datashaman/community-kind/tree/test-release')
        ->assertSee('without warranty')
        ->assertSee('AGPL-3.0-only');
});

it('serves the complete licence text', function (): void {
    $this->get(route('source-and-licence.license'))
        ->assertOk()
        ->assertHeader('content-type', 'text/plain; charset=UTF-8')
        ->assertSee('GNU AFFERO GENERAL PUBLIC LICENSE');
});
