<?php

use App\Actions\Mentorship\BookMeeting;
use App\Actions\Mentorship\SubmitMeetingReport;
use App\Enums\UserRole;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Models\User;
use App\Notifications\ReportAvailable;
use App\Notifications\ReportSubmitted;
use Illuminate\Support\Facades\Notification;

function reportMeeting(): array
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
    $meeting = app(BookMeeting::class)->handle($pairing, $slot);

    return [$entrepreneur, $mentor, $meeting];
}

test('submitting a report notifies every admin and the entrepreneur in app only', function () {
    Notification::fake();
    [$entrepreneur, $mentor, $meeting] = reportMeeting();
    $admin1 = User::factory()->create(['role' => UserRole::Admin]);
    $admin2 = User::factory()->create(['role' => UserRole::Admin]);

    app(SubmitMeetingReport::class)->handle($meeting, $mentor, 'A productive session on fundraising.');

    Notification::assertSentTo([$admin1, $admin2], ReportSubmitted::class, function ($notification, $channels) {
        return $channels === ['database', 'broadcast'];
    });
    Notification::assertSentTo($entrepreneur, ReportAvailable::class, function ($notification, $channels) {
        return $channels === ['database', 'broadcast'];
    });
    Notification::assertNotSentTo($mentor, ReportSubmitted::class);
    Notification::assertNotSentTo($mentor, ReportAvailable::class);
});

test('the report notifications carry hyphen free titles and correct actions', function () {
    Notification::fake();
    [$entrepreneur, , $meeting] = reportMeeting();
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $adminData = (new ReportSubmitted($meeting))->toArray($admin);
    expect($adminData['category'])->toBe('report')
        ->and($adminData['title'])->toBe('New meeting report')
        ->and($adminData['title'])->not->toContain('-');

    $entData = (new ReportAvailable($meeting))->toArray($entrepreneur);
    expect($entData['title'])->toBe('Session report ready')
        ->and($entData['title'])->not->toContain('-')
        ->and($entData['actions'][0]['url'])->toBe('/entrepreneur/meetings');
});
