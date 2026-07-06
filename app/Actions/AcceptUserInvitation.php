<?php

namespace App\Actions;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\DB;

class AcceptUserInvitation
{
    /**
     * Consume a (already validated as pending) invitation: create the user with
     * the invited email and role, mark the email verified (acceptance proves
     * control of the address), create the role profile, and burn the invitation.
     */
    public function handle(UserInvitation $invitation, string $name, string $password): User
    {
        return DB::transaction(function () use ($invitation, $name, $password) {
            $role = $invitation->role;

            // The invitation *is* the approval: an admin only sends one after an
            // offline background check, so every accepted user starts approved.
            $user = User::create([
                'name' => $name,
                'email' => $invitation->email,
                'password' => $password,
                'role' => $role,
                'account_status' => AccountStatus::Approved,
                'company_id' => $invitation->company_id,
                'account_status_changed_at' => now(),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();

            if ($role === UserRole::Entrepreneur) {
                $user->entrepreneurProfile()->create([]);
            } elseif ($role === UserRole::Mentor) {
                $user->mentorProfile()->create([]);
            }

            $invitation->forceFill([
                'accepted_at' => now(),
                'accepted_user_id' => $user->id,
            ])->save();

            return $user;
        });
    }
}
