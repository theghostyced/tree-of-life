<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => UserRole::Entrepreneur,
            'account_status' => AccountStatus::Approved,
            'phone_number' => '+254'.fake()->unique()->numerify('#########'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function mentor(): static
    {
        return $this->state(fn () => ['role' => UserRole::Mentor])
            ->afterCreating(fn (User $user) => $user->mentorProfile()->create([]));
    }

    public function entrepreneur(): static
    {
        return $this->state(fn () => ['role' => UserRole::Entrepreneur])
            ->afterCreating(fn (User $user) => $user->entrepreneurProfile()->create([]));
    }

    public function employee(): static
    {
        return $this->state(fn () => ['role' => UserRole::Employee]);
    }

    public function approved(): static
    {
        return $this->state(fn () => ['account_status' => AccountStatus::Approved]);
    }

    public function deactivated(): static
    {
        return $this->state(fn () => ['account_status' => AccountStatus::Deactivated]);
    }
}
