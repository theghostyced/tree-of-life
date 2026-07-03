<?php

namespace Database\Seeders;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the bootstrap admin. Because Tolfund is invitation-only and every
     * account needs an inviter, the first admin cannot be invited — it is
     * seeded here. This admin can then invite further admins, mentors, and
     * entrepreneurs. Idempotent: safe to run repeatedly.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@tolfund.com'],
            [
                'name' => 'Tolfund Admin',
                // Plain value; the User model's 'hashed' cast hashes it on save.
                'password' => env('ADMIN_PASSWORD', 'password'),
                'role' => UserRole::Admin,
                'account_status' => AccountStatus::Approved,
                'email_verified_at' => now(),
                'account_status_changed_at' => now(),
            ]
        );
    }
}
