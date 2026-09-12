<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('a user uploads a profile picture to the private disk', function () {
    Storage::fake('local');
    $user = User::factory()->mentor()->approved()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'avatar' => UploadedFile::fake()->image('me.png', 256, 256),
    ])->assertRedirect();

    $user->refresh();
    expect($user->avatar_path)->not->toBeNull()
        ->and($user->avatar_path)->toStartWith("avatars/{$user->id}/")
        ->and($user->avatar_path)->not->toContain('public');
    Storage::disk('local')->assertExists($user->avatar_path);
});

test('replacing a profile picture deletes the previous file', function () {
    Storage::fake('local');
    $user = User::factory()->mentor()->approved()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'avatar' => UploadedFile::fake()->image('first.png', 256, 256),
    ]);
    $first = $user->fresh()->avatar_path;

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'avatar' => UploadedFile::fake()->image('second.jpg', 256, 256),
    ]);

    Storage::disk('local')->assertMissing($first);
    Storage::disk('local')->assertExists($user->fresh()->avatar_path);
});

test('a user can remove their profile picture', function () {
    Storage::fake('local');
    $user = User::factory()->mentor()->approved()->create();
    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'avatar' => UploadedFile::fake()->image('me.png', 256, 256),
    ]);
    $path = $user->fresh()->avatar_path;

    $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'remove_avatar' => true])
        ->assertRedirect();

    expect($user->fresh()->avatar_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

test('only small JPG or PNG images are accepted', function (UploadedFile $file) {
    Storage::fake('local');
    $user = User::factory()->mentor()->approved()->create();

    $this->actingAs($user)->from('/profile')
        ->patch('/profile', ['name' => $user->name, 'avatar' => $file])
        ->assertSessionHasErrors('avatar');

    expect($user->fresh()->avatar_path)->toBeNull();
})->with([
    'a pdf' => fn () => UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf'),
    'a gif' => fn () => UploadedFile::fake()->image('anim.gif', 256, 256),
    'too large' => fn () => UploadedFile::fake()->image('huge.png', 256, 256)->size(3000),
    'too small' => fn () => UploadedFile::fake()->image('tiny.png', 32, 32),
]);

test('the avatar is streamed inline to any signed-in user and shared as a prop', function () {
    Storage::fake('local');
    $owner = User::factory()->mentor()->approved()->create();
    $viewer = User::factory()->entrepreneur()->approved()->create();

    $this->actingAs($owner)->patch('/profile', [
        'name' => $owner->name,
        'avatar' => UploadedFile::fake()->image('me.png', 256, 256),
    ]);

    $this->actingAs($viewer)->get("/users/{$owner->id}/avatar")
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($owner)->get('/profile')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.avatarUrl', route('users.avatar', $owner, absolute: false))
            ->where('profile.avatarUrl', route('users.avatar', $owner, absolute: false)));
});

test('the avatar route is not public and 404s when there is no picture', function () {
    $owner = User::factory()->mentor()->approved()->create();

    $this->get("/users/{$owner->id}/avatar")->assertRedirect('/login');
    $this->actingAs($owner)->get("/users/{$owner->id}/avatar")->assertNotFound();
});
