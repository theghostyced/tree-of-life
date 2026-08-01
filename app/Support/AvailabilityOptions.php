<?php

namespace App\Support;

use App\Actions\Mentorship\BookMeeting;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;

class AvailabilityOptions
{
    /**
     * Every free upcoming occurrence of the mentor's active slots for this
     * pairing, oldest first. Shared by booking and rescheduling so both offer
     * exactly the times the mentor has published.
     *
     * @return list<array<string, mixed>>
     */
    public static function forPairing(Pairing $pairing): array
    {
        $slots = MentorAvailabilitySlot::query()
            ->where('mentor_user_id', $pairing->mentor_user_id)
            ->where('is_active', true)
            ->get();

        $occurrences = [];

        foreach ($slots as $slot) {
            $duration = BookMeeting::durationMinutes($slot);

            foreach (BookMeeting::freeOccurrences($slot, $pairing) as $occurrence) {
                $occurrences[] = [
                    'slotId' => $slot->id,
                    'startsAt' => $occurrence->getTimestampMs(),
                    'endsAt' => $occurrence->copy()->addMinutes($duration)->getTimestampMs(),
                    'sessionType' => $slot->session_type,
                    'location' => $slot->location,
                    'meetingLink' => $slot->meeting_link,
                ];
            }
        }

        usort($occurrences, fn ($a, $b) => $a['startsAt'] <=> $b['startsAt']);

        return $occurrences;
    }
}
