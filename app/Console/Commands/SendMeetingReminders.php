<?php

namespace App\Console\Commands;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingNotificationDispatch;
use App\Notifications\MeetingReminder;
use App\Notifications\ReportDue;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Scans confirmed meetings for the three time based reminders and dispatches
 * each exactly once via the dispatch ledger. Safe to run every few minutes.
 */
class SendMeetingReminders extends Command
{
    protected $signature = 'notifications:send-meeting-reminders';

    protected $description = 'Send meeting reminders and report reminders that have come due';

    public function handle(): int
    {
        $now = now();

        // A day before the call (more than an hour out, up to a day). Both
        // parties, email only.
        $this->send('reminder_day',
            Meeting::query()->where('status', MeetingStatus::Confirmed)
                ->where('starts_at', '>', $now->addHour())
                ->where('starts_at', '<=', $now->addDay())
                ->with('pairing.mentor', 'pairing.entrepreneur')->get(),
            fn (Meeting $m) => [
                [$m->pairing->mentor, new MeetingReminder($m, 'day')],
                [$m->pairing->entrepreneur, new MeetingReminder($m, 'day')],
            ],
        );

        // An hour before the call. Both parties, email and in app.
        $this->send('reminder_hour',
            Meeting::query()->where('status', MeetingStatus::Confirmed)
                ->where('starts_at', '>', $now)
                ->where('starts_at', '<=', $now->addHour())
                ->with('pairing.mentor', 'pairing.entrepreneur')->get(),
            fn (Meeting $m) => [
                [$m->pairing->mentor, new MeetingReminder($m, 'hour')],
                [$m->pairing->entrepreneur, new MeetingReminder($m, 'hour')],
            ],
        );

        // An hour after the start, if no report has been filed. Mentor only.
        $this->send('report_due',
            Meeting::query()->where('status', MeetingStatus::Confirmed)
                ->where('starts_at', '<=', $now->subHour())
                ->whereDoesntHave('report')
                ->with('pairing.mentor', 'pairing.entrepreneur')->get(),
            fn (Meeting $m) => [
                [$m->pairing->mentor, new ReportDue($m)],
            ],
        );

        return self::SUCCESS;
    }

    /**
     * Dispatch a kind for each meeting exactly once, guarded by the ledger.
     *
     * @param  Collection<int, Meeting>  $meetings
     * @param  callable(Meeting): array<int, array{0: mixed, 1: mixed}>  $targets
     */
    private function send(string $kind, Collection $meetings, callable $targets): void
    {
        foreach ($meetings as $meeting) {
            $ledger = MeetingNotificationDispatch::firstOrCreate(
                ['meeting_id' => $meeting->id, 'kind' => $kind],
                ['dispatched_at' => now()],
            );

            if (! $ledger->wasRecentlyCreated) {
                continue;
            }

            foreach ($targets($meeting) as [$notifiable, $notification]) {
                $notifiable->notify($notification);
            }
        }
    }
}
