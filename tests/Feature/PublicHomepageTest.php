<?php

use Inertia\Testing\AssertableInertia as Assert;

it('renders the public product introduction', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('welcome')
            ->where('auth.user', null));
});
