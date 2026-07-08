<?php

namespace App\Actions\Onboarding;

use App\Data\OnboardingProgress;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\OnboardingCompleted;
use App\Notifications\UserCompletedOnboarding;
use Illuminate\Support\Facades\Notification;

class MarkOnboardingComplete
{
    /**
     * If the user just finished onboarding (and was not marked complete before),
     * stamp the moment and notify them plus the admins. Idempotent: the
     * timestamp guarantees this fires exactly once.
     */
    public function handle(User $user): void
    {
        $user->refresh();

        if ($user->onboarding_completed_at !== null) {
            return;
        }
        if (! OnboardingProgress::forUser($user)->isComplete) {
            return;
        }

        $user->forceFill(['onboarding_completed_at' => now()])->save();

        $user->notify(new OnboardingCompleted);
        Notification::send(
            User::query()->where('role', UserRole::Admin)->get(),
            new UserCompletedOnboarding($user),
        );
    }
}
