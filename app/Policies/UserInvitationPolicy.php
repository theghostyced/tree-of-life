<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserInvitation;

class UserInvitationPolicy
{
    /**
     * Issuing and managing invitations is an approved-admin capability.
     */
    private function manages(User $user): bool
    {
        return $user->isAdmin() && $user->isApproved();
    }

    public function viewAny(User $user): bool
    {
        return $this->manages($user);
    }

    public function view(User $user, UserInvitation $invitation): bool
    {
        return $this->manages($user);
    }

    public function create(User $user): bool
    {
        return $this->manages($user);
    }

    /**
     * An invitation can only be revoked while it is still pending — once it has
     * been accepted, revoked, or has expired there is nothing to revoke.
     */
    public function revoke(User $user, UserInvitation $invitation): bool
    {
        return $this->manages($user) && $invitation->isPending();
    }

    public function resend(User $user, UserInvitation $invitation): bool
    {
        return $this->manages($user) && $invitation->isPending();
    }

    public function delete(User $user, UserInvitation $invitation): bool
    {
        return $this->revoke($user, $invitation);
    }
}
