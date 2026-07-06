<?php

namespace App\Models;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property AccountStatus $account_status
 * @property string|null $phone_number
 * @property int|null $company_id
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $account_status_changed_at
 * @property Carbon|null $profile_submitted_at
 * @property string $password
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'email', 'password', 'role', 'account_status', 'phone_number',
    'company_id', 'account_status_changed_at', 'profile_submitted_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, MustVerifyEmail, Notifiable, SoftDeletes;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'account_status_changed_at' => 'datetime',
            'profile_submitted_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'account_status' => AccountStatus::class,
        ];
    }

    /** @return HasOne<EntrepreneurProfile, $this> */
    public function entrepreneurProfile(): HasOne
    {
        return $this->hasOne(EntrepreneurProfile::class);
    }

    /** @return HasOne<MentorProfile, $this> */
    public function mentorProfile(): HasOne
    {
        return $this->hasOne(MentorProfile::class);
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasOne<Company, $this> */
    public function ownedCompany(): HasOne
    {
        return $this->hasOne(Company::class, 'owner_id');
    }

    /** @return HasMany<UserInvitation, $this> */
    public function sentInvitations(): HasMany
    {
        return $this->hasMany(UserInvitation::class, 'invited_by');
    }

    /** @return HasMany<UserDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(UserDocument::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isMentor(): bool
    {
        return $this->role === UserRole::Mentor;
    }

    public function isEntrepreneur(): bool
    {
        return $this->role === UserRole::Entrepreneur;
    }

    public function isEmployee(): bool
    {
        return $this->role === UserRole::Employee;
    }

    public function isApproved(): bool
    {
        return $this->account_status === AccountStatus::Approved;
    }

    public function isDeactivated(): bool
    {
        return $this->account_status === AccountStatus::Deactivated;
    }
}
