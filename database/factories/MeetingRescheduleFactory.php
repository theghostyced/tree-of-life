<?php

namespace Database\Factories;

use App\Enums\RescheduleStatus;
use App\Models\Meeting;
use App\Models\MeetingReschedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingReschedule>
 */
class MeetingRescheduleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory(),
            'requested_by_user_id' => null, // resolved in configure()
            'status' => RescheduleStatus::Pending,
            'reason' => fake()->sentence(),
            'previous_starts_at' => null,
            'previous_ends_at' => null,
            'new_starts_at' => null,
            'new_ends_at' => null,
            'reviewed_by_user_id' => null,
            'reviewed_at' => null,
        ];
    }

    public function configure(): static
    {
        // Derive requester and times from the meeting so states stay coherent.
        return $this->afterMaking(function (MeetingReschedule $reschedule) {
            $meeting = Meeting::find($reschedule->meeting_id) ?? Meeting::factory()->create();
            $reschedule->meeting_id = $meeting->id;
            $reschedule->requested_by_user_id ??= $meeting->pairing->entrepreneur_user_id;
            $reschedule->previous_starts_at ??= $meeting->starts_at;
            $reschedule->previous_ends_at ??= $meeting->ends_at;
            $reschedule->new_starts_at ??= $meeting->starts_at->copy()->addDays(2);
            $reschedule->new_ends_at ??= $meeting->ends_at->copy()->addDays(2);
        });
    }
}
