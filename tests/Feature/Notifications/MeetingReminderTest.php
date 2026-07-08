<?php

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\Pairing;
use App\Notifications\MeetingReminder;
use App\Notifications\ReportDue;
use Illuminate\Support\Facades\Notification;

function reminderMeeting($startsAt, bool $withReport = false): array
{
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);
    $meeting = Meeting::create([
        'pairing_id' => $pairing->id,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHour(),
        'timezone' => 'UTC',
        'session_type' => 'virtual',
        'status' => MeetingStatus::Confirmed,
        'confirmed_at' => now(),
    ]);
    if ($withReport) {
        MeetingReport::create([
            'meeting_id' => $meeting->id,
            'submitted_by_user_id' => $mentor->id,
            'summary' => 'It went well.',
            'submitted_at' => now(),
        ]);
    }

    return [$entrepreneur, $mentor, $meeting];
}

test('a meeting a day away reminds both parties by email only', function () {
    Notification::fake();
    [$entrepreneur, $mentor] = reminderMeeting(now()->addHours(23));

    $this->artisan('notifications:send-meeting-reminders')->assertSuccessful();

    Notification::assertSentTo($mentor, MeetingReminder::class, function ($n, $channels) {
        return $n->lead === 'day' && $channels === ['mail'];
    });
    Notification::assertSentTo($entrepreneur, MeetingReminder::class, fn ($n) => $n->lead === 'day');
});

test('a meeting within the hour reminds both parties in app and email', function () {
    Notification::fake();
    [$entrepreneur, $mentor] = reminderMeeting(now()->addMinutes(30));

    $this->artisan('notifications:send-meeting-reminders');

    Notification::assertSentTo($mentor, MeetingReminder::class, function ($n, $channels) {
        return $n->lead === 'hour'
            && in_array('database', $channels)
            && in_array('mail', $channels)
            && in_array('broadcast', $channels);
    });
    // The hour reminder is exclusive of the day reminder.
    Notification::assertSentToTimes($mentor, MeetingReminder::class, 1);
});

test('a call an hour past its start with no report reminds the mentor only', function () {
    Notification::fake();
    [$entrepreneur, $mentor] = reminderMeeting(now()->subHours(2));

    $this->artisan('notifications:send-meeting-reminders');

    Notification::assertSentTo($mentor, ReportDue::class);
    Notification::assertNotSentTo($entrepreneur, ReportDue::class);
});

test('a filed report suppresses the report reminder', function () {
    Notification::fake();
    [, $mentor] = reminderMeeting(now()->subHours(2), withReport: true);

    $this->artisan('notifications:send-meeting-reminders');

    Notification::assertNotSentTo($mentor, ReportDue::class);
});

test('running the scan twice does not send a reminder twice', function () {
    Notification::fake();
    [, $mentor] = reminderMeeting(now()->addMinutes(30));

    $this->artisan('notifications:send-meeting-reminders');
    $this->artisan('notifications:send-meeting-reminders');

    Notification::assertSentToTimes($mentor, MeetingReminder::class, 1);
});
