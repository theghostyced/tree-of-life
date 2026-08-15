<?php

use App\Models\User;

/**
 * app.ts imports the Echo module before mounting Inertia, so anything thrown
 * while that module evaluates aborts the whole application bootstrap rather
 * than only breaking chat. `data-hydrated` is set once createInertiaApp
 * resolves, which makes it the signal that the app actually came up.
 */
it('boots the public pages', function (string $path) {
    visit($path)
        ->assertPresent('html[data-hydrated=true]')
        ->assertNoJavaScriptErrors();
})->with([
    'landing' => '/',
    'login' => '/login',
]);

it('renders the brand mark in the chrome', function () {
    visit('/')->assertPresent('img[src="/images/brand/tree-of-life.png"]');
});

it('boots a signed-in page that subscribes to realtime', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();

    $this->actingAs($entrepreneur);

    visit('/entrepreneur/messages')
        ->assertPresent('html[data-hydrated=true]')
        ->assertNoJavaScriptErrors();
});
