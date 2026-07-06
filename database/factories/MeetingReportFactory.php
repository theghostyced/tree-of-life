<?php

namespace Database\Factories;

use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MeetingReport>
 */
class MeetingReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'meeting_id' => Meeting::factory()->completed(),
            'submitted_by_user_id' => null, // resolved in configure()
            'summary' => fake()->paragraph(),
            'submitted_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (MeetingReport $report) {
            $meeting = Meeting::find($report->meeting_id);
            $report->submitted_by_user_id ??= $meeting?->pairing->mentor_user_id
                ?? User::factory()->mentor()->approved()->create()->id;
        });
    }
}
