<?php

use App\Actions\Onboarding\MarkOnboardingComplete;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\OnboardingCompleted;
use App\Notifications\UserCompletedOnboarding;
use Illuminate\Support\Facades\Notification;

test('finishing onboarding notifies the user and all admins', function () {
    Notification::fake();
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $entrepreneur = completeEntrepreneur();

    app(MarkOnboardingComplete::class)->handle($entrepreneur);

    Notification::assertSentTo($entrepreneur, OnboardingCompleted::class, function ($notification, $channels) {
        return in_array('database', $channels) && in_array('mail', $channels);
    });
    Notification::assertSentTo($admin, UserCompletedOnboarding::class, function ($notification, $channels) {
        return $channels === ['database', 'broadcast'];
    });
    expect($entrepreneur->fresh()->onboarding_completed_at)->not->toBeNull();
});

test('the completion notification fires only once', function () {
    Notification::fake();
    $entrepreneur = completeEntrepreneur();

    app(MarkOnboardingComplete::class)->handle($entrepreneur);
    app(MarkOnboardingComplete::class)->handle($entrepreneur);

    Notification::assertSentToTimes($entrepreneur, OnboardingCompleted::class, 1);
});

test('an incomplete profile does not fire the completion notification', function () {
    Notification::fake();
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();

    app(MarkOnboardingComplete::class)->handle($entrepreneur);

    Notification::assertNotSentTo($entrepreneur, OnboardingCompleted::class);
    expect($entrepreneur->fresh()->onboarding_completed_at)->toBeNull();
});

test('the completion notification is role aware and hyphen free', function () {
    $entrepreneur = completeEntrepreneur();
    $data = (new OnboardingCompleted)->toArray($entrepreneur);
    expect($data['category'])->toBe('onboarding')
        ->and($data['title'])->toBe('You are all set')
        ->and($data['title'])->not->toContain('-')
        ->and($data['actions'][0]['url'])->toBe('/entrepreneur/mentors');
});
