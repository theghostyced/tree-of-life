<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Models\User;

class ApproveUser
{
    public function handle(User $user): void
    {
        $user->forceFill([
            'account_status' => AccountStatus::Approved,
            'account_status_changed_at' => now(),
        ])->save();
    }
}
