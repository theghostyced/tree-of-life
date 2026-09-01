<?php

namespace App\Actions;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Str;

class CreateUserInvitation
{
    /**
     * Create an invitation, storing only the SHA-256 hash of a fresh raw token.
     * The raw token is returned once so it can be placed in the emailed link.
     *
     * @return array{0: UserInvitation, 1: string}
     */
    public function handle(
        string $email,
        UserRole $role,
        User $invitedBy,
        ?int $companyId = null,
        ?string $name = null,
    ): array {
        $token = Str::random(64);

        $invitation = UserInvitation::create([
            'email' => $email,
            'role' => $role,
            'token_hash' => hash('sha256', $token),
            'invited_by' => $invitedBy->id,
            'company_id' => $companyId,
            'name' => $name,
            'expires_at' => now()->addDays(config('invitations.expiry_days')),
        ]);

        return [$invitation, $token];
    }
}
