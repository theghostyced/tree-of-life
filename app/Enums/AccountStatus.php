<?php

namespace App\Enums;

enum AccountStatus: string
{
    // The invitation is the approval, so an accepted account is Approved; an
    // admin can later Deactivate it (and reactivate). There are no other states.
    case Approved = 'approved';
    case Deactivated = 'deactivated';

    /**
     * Human-readable label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Allowed lifecycle transitions: an active account can be deactivated, and a
     * deactivated one reactivated.
     */
    public function canTransitionTo(AccountStatus $to): bool
    {
        return match ($this) {
            self::Approved => $to === self::Deactivated,
            self::Deactivated => $to === self::Approved,
        };
    }
}
