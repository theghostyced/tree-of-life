<?php

namespace App\Actions\Mentorship;

use App\Actions\Chat\PostSystemMessage;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class BookMeeting
{
    /**
     * Book a mentor's slot for a pairing. Without $startsAt, books the next
     * free occurrence; with it, books that specific (still-available) occurrence.
     */
    public function handle(
        Pairing $pairing,
        MentorAvailabilitySlot $slot,
        ?CarbonInterface $startsAt = null,
    ): Meeting {
        if ($startsAt !== null) {
            $starts = self::freeOccurrences($slot, $pairing)
                ->first(fn (CarbonInterface $o) => $o->equalTo($startsAt));
            abort_if($starts === null, 422, 'That time is no longer available.');
        } else {
            $starts = self::nextFreeOccurrence($slot, $pairing);
            abort_if($starts === null, 422, 'That slot is fully booked for the coming weeks.');
        }

        $meeting = Meeting::create([
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

        // Announce the booking in the pairing's chat. Kept defensive so a
        // booking never fails if a legacy pairing has no conversation.
        if ($conversation = $pairing->conversation()->first()) {
            try {
                $when = $meeting->starts_at->timezone($meeting->timezone)->format('D j M, g:i A');
                app(PostSystemMessage::class)->handle($conversation, "📅 Call scheduled for {$when}");
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $meeting;
    }

    /**
     * The earliest future free occurrence of the slot for this pairing.
     */
    public static function nextFreeOccurrence(MentorAvailabilitySlot $slot, Pairing $pairing): ?CarbonInterface
    {
        return self::freeOccurrences($slot, $pairing)->first();
    }

    /**
     * All future occurrences of the slot's weekday+time this pairing hasn't
     * already booked, over the next $weeks weeks (oldest first).
     *
     * @return Collection<int, CarbonInterface>
     */
    public static function freeOccurrences(
        MentorAvailabilitySlot $slot,
        Pairing $pairing,
        int $weeks = 8,
    ): Collection {
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

        $out = collect();
        for ($week = 0; $week < $weeks; $week++) {
            $taken = $pairing->meetings()
                ->where('status', MeetingStatus::Confirmed->value)
                ->where('starts_at', $candidate->toDateTimeString())
                ->exists();

            if (! $taken) {
                $out->push($candidate->copy());
            }

            $candidate = $candidate->addWeek();
        }

        return $out;
    }

    public static function durationMinutes(MentorAvailabilitySlot $slot): int
    {
        [$sh, $sm] = array_map('intval', explode(':', substr((string) $slot->start_time, 0, 5)));
        [$eh, $em] = array_map('intval', explode(':', substr((string) $slot->end_time, 0, 5)));

        return ($eh * 60 + $em) - ($sh * 60 + $sm);
    }
}
