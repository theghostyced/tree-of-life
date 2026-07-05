<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('a role mismatch renders the designed 403 page', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();

    $this->actingAs($entrepreneur)
        ->get('/admin/dashboard')
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shared/Error')
            ->where('status', 403));
});

test('an unknown url renders the designed 404 page', function () {
    $this->get('/definitely/not/a/page')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shared/Error')
            ->where('status', 404));
});

test('a server error renders the designed 500 page when debug is off', function () {
    config(['app.debug' => false]);
    Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('boom'));

    $this->withExceptionHandling()
        ->get('/_test/boom')
        ->assertServerError()
        ->assertInertia(fn (Assert $page) => $page
            ->component('shared/Error')
            ->where('status', 500));
});

test('a server error keeps the framework debug page when debug is on', function () {
    config(['app.debug' => true]);
    Route::middleware('web')->get('/_test/boom', fn () => throw new RuntimeException('boom'));

    $response = $this->withExceptionHandling()->get('/_test/boom');

    $response->assertServerError();
    expect($response->headers->get('X-Inertia'))->toBeNull();
});

test('json requests are not intercepted by the error page', function () {
    $this->getJson('/definitely/not/a/page')
        ->assertNotFound()
        ->assertJsonStructure(['message']);
});
