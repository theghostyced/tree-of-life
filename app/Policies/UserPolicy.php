<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function view(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Profile pictures appear in chat, cards and the navbar, so any signed-in
     * user may load anyone's.
     */
    public function viewAvatar(User $actor, User $target): bool
    {
        return true;
    }

    /**
     * Revoking access. An admin cannot lock themselves out, and an account that
     * is already deactivated has nothing to revoke.
     */
    public function deactivate(User $actor, User $target): bool
    {
        return $actor->isAdmin()
            && $actor->isNot($target)
            && $target->isApproved();
    }

    public function reactivate(User $actor, User $target): bool
    {
        return $actor->isAdmin() && $target->isDeactivated();
    }

    /**
     * Soft-deleting. An admin cannot delete their own account.
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->isAdmin() && $actor->isNot($target);
    }
}
