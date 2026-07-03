<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDocument;

class UserDocumentPolicy
{
    /**
     * A private document is visible to the user who owns it and to admins
     * (who review onboarding submissions). No one else may see it.
     */
    public function view(User $user, UserDocument $document): bool
    {
        return $user->id === $document->user_id || $user->isAdmin();
    }

    public function delete(User $user, UserDocument $document): bool
    {
        return $user->id === $document->user_id || $user->isAdmin();
    }
}
