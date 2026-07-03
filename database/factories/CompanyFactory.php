<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Company>
 */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'owner_id' => User::factory()->entrepreneur()->approved(),
            'name' => fake()->company(),
            'sector' => fake()->word(),
            'country' => fake()->country(),
        ];
    }
}
