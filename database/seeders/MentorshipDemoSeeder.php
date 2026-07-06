<?php

namespace Database\Seeders;

use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MeetingReschedule;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local-only: gives the seeded demo mentor a believable week so the
 * dashboard renders with life. Idempotent per mentor (skips if the mentor
 * already has pairings).
 */
class MentorshipDemoSeeder extends Seeder
{
    public function run(): void
    {
        $mentor = User::query()->where('email', 'grace@example.com')->first()
            ?? User::factory()->mentor()->approved()->create([
                'name' => 'Grace Mentor',
                'email' => 'grace@example.com',
            ]);

        if (Pairing::query()->where('mentor_user_id', $mentor->id)->exists()) {
            return;
        }

        $pairings = Pairing::factory()->count(3)->create(['mentor_user_id' => $mentor->id]);

        MentorAvailabilitySlot::factory()->create(['mentor_user_id' => $mentor->id, 'day_of_week' => 1]);
        MentorAvailabilitySlot::factory()->create(['mentor_user_id' => $mentor->id, 'day_of_week' => 3, 'start_time' => '14:00', 'end_time' => '15:00']);

        // Upcoming meetings this week.
        Meeting::factory()->create(['pairing_id' => $pairings[0]->id]);
        Meeting::factory()->create([
            'pairing_id' => $pairings[1]->id,
            'starts_at' => now()->addDays(3)->setTime(14, 0),
            'ends_at' => now()->addDays(3)->setTime(15, 0),
            'meeting_link' => 'https://meet.google.com/demo-link',
        ]);

        // A pending reschedule from a mentee.
        $toMove = Meeting::factory()->create(['pairing_id' => $pairings[2]->id]);
        MeetingReschedule::factory()->create(['meeting_id' => $toMove->id]);

        // History: one reported, one awaiting its report.
        $reported = Meeting::factory()->completed()->create(['pairing_id' => $pairings[0]->id]);
        MeetingReport::factory()->create(['meeting_id' => $reported->id]);
        Meeting::factory()->completed()->create(['pairing_id' => $pairings[1]->id]);

        // An ended pairing for the admin detail page.
        Pairing::factory()->ended()->create(['mentor_user_id' => $mentor->id]);
    }
}
