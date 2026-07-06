<?php

namespace Database\Factories;

use App\Enums\PairingStatus;
use App\Models\Pairing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pairing>
 */
class PairingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'entrepreneur_user_id' => User::factory()->entrepreneur()->approved(),
            'mentor_user_id' => User::factory()->mentor()->approved(),
            'status' => PairingStatus::Active,
            'ended_at' => null,
        ];
    }

    public function ended(): static
    {
        return $this->state(fn () => [
            'status' => PairingStatus::Ended,
            'ended_at' => now()->subDays(3),
        ]);
    }
}
