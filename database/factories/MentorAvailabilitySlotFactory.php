<?php

namespace Database\Factories;

use App\Models\MentorAvailabilitySlot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MentorAvailabilitySlot>
 */
class MentorAvailabilitySlotFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mentor_user_id' => User::factory()->mentor()->approved(),
            'day_of_week' => fake()->numberBetween(0, 4),
            'start_time' => '09:00',
            'end_time' => '10:00',
            'timezone' => 'Africa/Nairobi',
            'session_type' => 'virtual',
            'location' => null,
            'meeting_link' => null,
            'is_active' => true,
        ];
    }
}
