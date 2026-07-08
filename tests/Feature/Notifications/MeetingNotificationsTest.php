<?php

use App\Actions\Mentorship\BookMeeting;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Notifications\MeetingBooked;
use App\Notifications\MeetingBookedConfirmation;
use Illuminate\Support\Facades\Notification;

function bookingSetup(): array
{
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);
    $slot = MentorAvailabilitySlot::factory()->for($mentor, 'mentor')->create([
        'day_of_week' => 2,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_active' => true,
    ]);

    return [$entrepreneur, $mentor, $pairing, $slot];
}

test('booking a meeting notifies the mentor and entrepreneur on the right channels', function () {
    Notification::fake();
    [$entrepreneur, $mentor, $pairing, $slot] = bookingSetup();

    app(BookMeeting::class)->handle($pairing, $slot);

    Notification::assertSentTo($mentor, MeetingBooked::class, function ($notification, $channels) {
        return in_array('database', $channels)
            && in_array('mail', $channels)
            && in_array('broadcast', $channels);
    });
    Notification::assertSentTo($entrepreneur, MeetingBookedConfirmation::class);
    Notification::assertNotSentTo($mentor, MeetingBookedConfirmation::class);
    Notification::assertNotSentTo($entrepreneur, MeetingBooked::class);
});

test('the booking notifications carry the right content and hyphen free titles', function () {
    Notification::fake();
    [$entrepreneur, $mentor, $pairing, $slot] = bookingSetup();
    $meeting = app(BookMeeting::class)->handle($pairing, $slot);

    $mentorData = (new MeetingBooked($meeting))->toArray($mentor);
    expect($mentorData['category'])->toBe('meeting')
        ->and($mentorData['title'])->toBe('New call booked')
        ->and($mentorData['title'])->not->toContain('-')
        ->and($mentorData['actions'][0]['url'])->toBe('/mentor/meetings');

    $entData = (new MeetingBookedConfirmation($meeting))->toArray($entrepreneur);
    expect($entData['title'])->toBe('Your call is booked')
        ->and($entData['title'])->not->toContain('-')
        ->and($entData['actions'][0]['url'])->toBe('/entrepreneur/meetings');

    $mail = (new MeetingBooked($meeting))->toMail($mentor);
    expect($mail->subject)->toBe('A new call has been booked');
});
