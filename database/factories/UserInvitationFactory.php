<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UserInvitation>
 */
class UserInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Entrepreneur,
            'token_hash' => hash('sha256', Str::random(64)),
            'invited_by' => User::factory()->admin()->approved(),
            'accepted_user_id' => null,
            'company_id' => null,
            'name' => null,
            'note' => null,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'revoked_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'accepted_at' => null,
            'revoked_at' => null,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['accepted_at' => now()]);
    }

    public function forRole(UserRole $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }
}
