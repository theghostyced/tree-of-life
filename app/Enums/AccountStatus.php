<?php

namespace App\Enums;

enum AccountStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Deactivated = 'deactivated';

    /**
     * Human-readable label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Only approved accounts unlock full role capabilities.
     */
    public function hasFullAccess(): bool
    {
        return $this === self::Approved;
    }

    /**
     * Allowed lifecycle transitions:
     * draft -> pending -> approved | rejected; approved -> deactivated; rejected -> pending.
     */
    public function canTransitionTo(AccountStatus $to): bool
    {
        return match ($this) {
            self::Draft => $to === self::Pending,
            self::Pending => in_array($to, [self::Approved, self::Rejected], true),
            self::Approved => $to === self::Deactivated,
            self::Rejected => $to === self::Pending,
            self::Deactivated => false,
        };
    }
}
