<?php

namespace Database\Factories;

use App\Models\EntrepreneurProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EntrepreneurProfile>
 */
class EntrepreneurProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->entrepreneur(),
        ];
    }
}
