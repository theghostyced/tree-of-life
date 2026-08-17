<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the how it works page is public and renders its own component', function () {
    $this->get('/how-it-works')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('HowItWorks'));
});

test('the how it works route is named so links can resolve it', function () {
    expect(route('how-it-works'))->toEndWith('/how-it-works');
});

test('the landing page still renders for guests alongside it', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});
