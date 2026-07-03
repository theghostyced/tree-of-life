<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Enums\UserRole;
use Database\Factories\UserInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $email
 * @property UserRole $role
 * @property string $token_hash
 * @property int $invited_by
 * @property int|null $accepted_user_id
 * @property int|null $company_id
 * @property string|null $name
 * @property string|null $note
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $revoked_at
 */
#[Fillable([
    'email', 'role', 'token_hash', 'invited_by', 'accepted_user_id',
    'company_id', 'name', 'note', 'expires_at', 'accepted_at', 'revoked_at',
])]
class UserInvitation extends Model
{
    /** @use HasFactory<UserInvitationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Lifecycle status derived from timestamps, not stored.
     */
    public function status(): InvitationStatus
    {
        return match (true) {
            $this->revoked_at !== null => InvitationStatus::Revoked,
            $this->accepted_at !== null => InvitationStatus::Accepted,
            $this->expires_at !== null && $this->expires_at->isPast() => InvitationStatus::Expired,
            default => InvitationStatus::Pending,
        };
    }

    public function isPending(): bool
    {
        return $this->status() === InvitationStatus::Pending;
    }

    /** @return BelongsTo<User, $this> */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }
}
