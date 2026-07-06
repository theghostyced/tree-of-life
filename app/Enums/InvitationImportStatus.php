<?php

namespace App\Enums;

/**
 * Lifecycle of a CSV invitation import. Stored on invitation_imports.status.
 */
enum InvitationImportStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return $this === self::Completed || $this === self::Failed;
    }
}
