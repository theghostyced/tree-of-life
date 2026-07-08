<?php

use App\Actions\Mentorship\ReviewMeetingReschedule;
use App\Enums\MeetingStatus;
use App\Enums\RescheduleStatus;
use App\Models\Meeting;
use App\Models\MeetingReschedule;
use App\Models\Pairing;
use App\Notifications\MeetingRescheduleReviewed;
use Illuminate\Support\Facades\Notification;

function rescheduleSetup(): array
{
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);
    $starts = now()->addDays(3);
    $meeting = Meeting::create([
        'pairing_id' => $pairing->id,
        'starts_at' => $starts,
        'ends_at' => $starts->addHour(),
        'timezone' => 'UTC',
        'session_type' => 'virtual',
        'status' => MeetingStatus::Confirmed,
        'confirmed_at' => now(),
    ]);
    $newStarts = now()->addDays(5);
    $reschedule = MeetingReschedule::create([
        'meeting_id' => $meeting->id,
        'requested_by_user_id' => $entrepreneur->id,
        'status' => RescheduleStatus::Pending,
        'previous_starts_at' => $meeting->starts_at,
        'previous_ends_at' => $meeting->ends_at,
        'new_starts_at' => $newStarts,
        'new_ends_at' => $newStarts->addHour(),
    ]);

    return [$entrepreneur, $mentor, $meeting, $reschedule];
}

test('accepting a reschedule notifies the requester on every channel', function () {
    Notification::fake();
    [$entrepreneur, $mentor, , $reschedule] = rescheduleSetup();

    app(ReviewMeetingReschedule::class)->handle($reschedule, $mentor, accept: true);

    Notification::assertSentTo($entrepreneur, MeetingRescheduleReviewed::class, function ($notification, $channels) {
        return $notification->accepted === true
            && in_array('database', $channels)
            && in_array('mail', $channels)
            && in_array('broadcast', $channels);
    });
    Notification::assertNotSentTo($mentor, MeetingRescheduleReviewed::class);
});

test('declining a reschedule notifies the requester', function () {
    Notification::fake();
    [$entrepreneur, $mentor, , $reschedule] = rescheduleSetup();

    app(ReviewMeetingReschedule::class)->handle($reschedule, $mentor, accept: false);

    Notification::assertSentTo($entrepreneur, MeetingRescheduleReviewed::class, fn ($n) => $n->accepted === false);
});

test('the reschedule notification carries hyphen free copy and the right action', function () {
    Notification::fake();
    [$entrepreneur, , , $reschedule] = rescheduleSetup();

    $data = (new MeetingRescheduleReviewed($reschedule, true))->toArray($entrepreneur);
    expect($data['category'])->toBe('meeting')
        ->and($data['title'])->toBe('Reschedule accepted')
        ->and($data['title'])->not->toContain('-')
        ->and($data['actions'][0]['url'])->toBe('/entrepreneur/meetings');
});
