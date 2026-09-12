<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\User;

/**
 * The one free-text field each role already writes about themselves during
 * onboarding, surfaced on the profile page as their "bio" so there is a single
 * source of truth rather than a second description to keep in sync.
 */
class RoleBio
{
    /**
     * @return array{label: string, hint: string, value: string|null}|null
     */
    public static function forUser(User $user): ?array
    {
        return match ($user->role) {
            UserRole::Mentor => [
                'label' => 'Your AfCFTA knowledge',
                'hint' => 'Entrepreneurs read this when choosing a mentor.',
                'value' => $user->mentorProfile?->afcfta_knowledge,
            ],
            UserRole::Entrepreneur => [
                'label' => 'What your business does',
                'hint' => 'Shown to admins reviewing your account; it is the same text as in your onboarding profile.',
                'value' => $user->entrepreneurProfile?->business_description,
            ],
            default => null,
        };
    }

    public static function store(User $user, ?string $value): void
    {
        match ($user->role) {
            UserRole::Mentor => $user->mentorProfile?->update(['afcfta_knowledge' => $value]),
            UserRole::Entrepreneur => $user->entrepreneurProfile?->update(['business_description' => $value]),
            default => null,
        };
    }
}
