<?php

namespace App\Enums;

/**
 * Lifecycle status of an invitation. Derived from the record's timestamps
 * (accepted_at / revoked_at / expires_at) rather than stored as a column.
 */
enum InvitationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Revoked = 'revoked';
    case Expired = 'expired';

    /**
     * Human-readable label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
