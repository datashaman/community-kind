<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the public product introduction', function () {
    $response = $this->get(route('home'));

    $response
        ->assertOk()
        ->assertSee('name="theme-color"', false)
        ->assertSee('#f4f7f5', false)
        ->assertSee('#0d1d29', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('auth.user', null));
});
