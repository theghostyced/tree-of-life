<?php

namespace App\Actions\Mentorship;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use Carbon\CarbonInterface;

class BookMeeting
{
    /**
     * Book the next free occurrence of a mentor's weekly slot for a pairing.
     */
    public function handle(Pairing $pairing, MentorAvailabilitySlot $slot): Meeting
    {
        $starts = self::nextFreeOccurrence($slot, $pairing);

        abort_if($starts === null, 422, 'That slot is fully booked for the coming weeks.');

        return Meeting::create([
            'pairing_id' => $pairing->id,
            'mentor_availability_slot_id' => $slot->id,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addMinutes(self::durationMinutes($slot)),
            'timezone' => $slot->timezone,
            'session_type' => $slot->session_type,
            'location' => $slot->location,
            'meeting_link' => $slot->meeting_link,
            'status' => MeetingStatus::Confirmed,
            'confirmed_at' => now(),
        ]);
    }

    /**
     * The earliest future occurrence of the slot's weekday+time that this
     * pairing hasn't already booked, looking up to 8 weeks ahead.
     */
    public static function nextFreeOccurrence(MentorAvailabilitySlot $slot, Pairing $pairing): ?CarbonInterface
    {
        [$hour, $minute] = array_map('intval', explode(':', substr((string) $slot->start_time, 0, 5)));

        // App days are 0 = Monday .. 6 = Sunday; Carbon ISO is 1 = Monday .. 7 = Sunday.
        $targetIso = ((int) $slot->day_of_week) + 1;

        // The slot's times are wall-clock in the mentor's own timezone; resolve
        // the instant there, then work in the app timezone (how starts_at is
        // stored) so the dedupe comparison lines up.
        $candidate = now($slot->timezone)->startOfDay()->setTime($hour, $minute);
        $candidate = $candidate->addDays(($targetIso - $candidate->dayOfWeekIso + 7) % 7);
        if ($candidate->isPast()) {
            $candidate = $candidate->addWeek();
        }
        $candidate = $candidate->setTimezone(config('app.timezone'));

        for ($week = 0; $week < 8; $week++) {
            $taken = $pairing->meetings()
                ->where('status', MeetingStatus::Confirmed->value)
                ->where('starts_at', $candidate->toDateTimeString())
                ->exists();

            if (! $taken) {
                return $candidate->copy();
            }

            $candidate = $candidate->addWeek();
        }

        return null;
    }

    private static function durationMinutes(MentorAvailabilitySlot $slot): int
    {
        [$sh, $sm] = array_map('intval', explode(':', substr((string) $slot->start_time, 0, 5)));
        [$eh, $em] = array_map('intval', explode(':', substr((string) $slot->end_time, 0, 5)));

        return ($eh * 60 + $em) - ($sh * 60 + $sm);
    }
}
