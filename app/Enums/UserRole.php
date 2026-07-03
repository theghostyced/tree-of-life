<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Mentor = 'mentor';
    case Entrepreneur = 'entrepreneur';
    case Employee = 'employee';

    /**
     * Human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::Mentor => 'Mentor',
            self::Entrepreneur => 'Entrepreneur',
            self::Employee => 'Employee',
        };
    }

    /**
     * Roles an admin may invite people as.
     *
     * @return array<int, self>
     */
    public static function invitable(): array
    {
        return [self::Entrepreneur, self::Mentor, self::Admin];
    }

    /**
     * Whether an inviter with this role may issue an invitation for the given role.
     */
    public function canInvite(UserRole $role): bool
    {
        return match ($this) {
            self::Admin => true,
            self::Entrepreneur => $role === self::Employee,
            default => false,
        };
    }

    public function hasProfile(): bool
    {
        return in_array($this, [self::Entrepreneur, self::Mentor], true);
    }

    public function onboardingPath(): string
    {
        return $this === self::Mentor ? '/mentor/onboarding' : '/entrepreneur/onboarding';
    }

    /**
     * Where a user of this role lands after authentication, given their approval state.
     */
    public function homePath(bool $approved): string
    {
        return match ($this) {
            self::Admin => '/admin/dashboard',
            self::Mentor => $approved ? '/mentor/dashboard' : '/mentor/onboarding',
            self::Entrepreneur => $approved ? '/entrepreneur/dashboard' : '/entrepreneur/onboarding',
            self::Employee => '/entrepreneur/dashboard',
        };
    }
}
