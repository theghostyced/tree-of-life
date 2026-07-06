<?php

namespace Database\Factories;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\Pairing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $starts = now()->addDay()->setTime(10, 0);

        return [
            'pairing_id' => Pairing::factory(),
            'mentor_availability_slot_id' => null,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'timezone' => 'Africa/Nairobi',
            'session_type' => 'virtual',
            'location' => null,
            'meeting_link' => null,
            'google_event_id' => null,
            'agenda' => fake()->sentence(),
            'status' => MeetingStatus::Confirmed,
            'outcome_summary' => null,
            'confirmed_at' => now(),
            'completed_at' => null,
            'cancelled_at' => null,
            'cancelled_by_user_id' => null,
        ];
    }

    public function completed(): static
    {
        $starts = now()->subDay()->setTime(10, 0);

        return $this->state(fn () => [
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addHour(),
            'status' => MeetingStatus::Completed,
            'completed_at' => $starts->copy()->addHour(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => MeetingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
