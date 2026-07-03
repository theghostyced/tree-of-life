<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Models\User;

class RejectUser
{
    /**
     * Reject a pending account. The reason is captured for the reviewer's
     * decision; a rejected user may revise their profile and resubmit.
     *
     * NOTE: reason is not yet persisted — a `rejection_reason` column and a
     * notification to the applicant are a planned follow-up.
     */
    public function handle(User $user, string $reason): void
    {
        $user->forceFill([
            'account_status' => AccountStatus::Rejected,
            'account_status_changed_at' => now(),
        ])->save();
    }
}
