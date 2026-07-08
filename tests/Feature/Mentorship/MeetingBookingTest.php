<?php

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use Inertia\Testing\AssertableInertia as Assert;

function pairedWithSlot(): array
{
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);
    $slot = MentorAvailabilitySlot::factory()->for($mentor, 'mentor')->create([
        'day_of_week' => 2,
        'start_time' => '09:00',
        'end_time' => '10:00',
        'is_active' => true,
    ]);

    return [$entrepreneur, $mentor, $slot];
}

test('an entrepreneur can book a meeting from a mentor’s slot', function () {
    [$entrepreneur, $mentor, $slot] = pairedWithSlot();

    $this->actingAs($entrepreneur)
        ->post('/entrepreneur/meetings', ['slot_id' => $slot->id])
        ->assertRedirect();

    $meeting = Meeting::sole();
    expect($meeting->status)->toBe(MeetingStatus::Confirmed)
        ->and($meeting->pairing->entrepreneur_user_id)->toBe($entrepreneur->id)
        ->and($meeting->pairing->mentor_user_id)->toBe($mentor->id)
        ->and($meeting->starts_at->dayOfWeekIso)->toBe(3); // Wednesday (app day 2 + 1)
});

test('booking again schedules the following week, not a duplicate', function () {
    [$entrepreneur, , $slot] = pairedWithSlot();

    $this->actingAs($entrepreneur)->post('/entrepreneur/meetings', ['slot_id' => $slot->id]);
    $this->actingAs($entrepreneur)->post('/entrepreneur/meetings', ['slot_id' => $slot->id]);

    $times = Meeting::pluck('starts_at')->map->timestamp;
    expect(Meeting::count())->toBe(2)
        ->and($times->unique()->count())->toBe(2);
});

test('an entrepreneur cannot book a slot of a mentor they are not paired with', function () {
    $entrepreneur = completeEntrepreneur();
    $stranger = availableMentor('Stranger');
    $slot = MentorAvailabilitySlot::factory()->for($stranger, 'mentor')->create();

    $this->actingAs($entrepreneur)
        ->post('/entrepreneur/meetings', ['slot_id' => $slot->id])
        ->assertForbidden();

    expect(Meeting::count())->toBe(0);
});

test('the entrepreneur meetings page lists mentors and their open slot occurrences', function () {
    [$entrepreneur, , $slot] = pairedWithSlot();
    $pairing = Pairing::sole();

    // The weekly slot yields 8 free occurrences over the booking horizon.
    $this->actingAs($entrepreneur)->get('/entrepreneur/meetings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('entrepreneur/Meetings')
            ->has('mentors', 1)
            ->has("availability.{$pairing->id}", 8)
            ->has('upcoming', 0));

    $first = \App\Actions\Mentorship\BookMeeting::freeOccurrences($slot, $pairing)->first();
    $this->actingAs($entrepreneur)->post('/entrepreneur/meetings', [
        'slot_id' => $slot->id,
        'starts_at' => $first->getTimestampMs(),
    ]);

    // Booking one occurrence leaves seven, and one upcoming meeting.
    $this->actingAs($entrepreneur)->get('/entrepreneur/meetings')
        ->assertInertia(fn (Assert $page) => $page
            ->has('upcoming', 1)
            ->has("availability.{$pairing->id}", 7));
});

test('an entrepreneur can book a specific future occurrence, not just the next', function () {
    [$entrepreneur, , $slot] = pairedWithSlot();
    $pairing = Pairing::sole();
    $third = \App\Actions\Mentorship\BookMeeting::freeOccurrences($slot, $pairing)[2];

    $this->actingAs($entrepreneur)
        ->post('/entrepreneur/meetings', ['slot_id' => $slot->id, 'starts_at' => $third->getTimestampMs()])
        ->assertRedirect();

    expect(Meeting::sole()->starts_at->timestamp)->toBe($third->timestamp);
});

test('booking a time that is not an open slot occurrence is rejected', function () {
    [$entrepreneur, , $slot] = pairedWithSlot();

    // 1pm tomorrow is not a valid occurrence of the Wednesday 09:00 slot.
    $bogus = now()->addDay()->setTime(13, 0);

    $this->actingAs($entrepreneur)
        ->post('/entrepreneur/meetings', ['slot_id' => $slot->id, 'starts_at' => $bogus->getTimestampMs()])
        ->assertStatus(422);

    expect(Meeting::count())->toBe(0);
});

test('the mentor meetings page lists their booked sessions', function () {
    [$entrepreneur, $mentor, $slot] = pairedWithSlot();
    $this->actingAs($entrepreneur)->post('/entrepreneur/meetings', ['slot_id' => $slot->id]);

    $this->actingAs($mentor)->get('/mentor/meetings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mentor/Meetings')
            ->has('upcoming', 1)
            ->where('upcoming.0.counterpartName', $entrepreneur->name));
});
