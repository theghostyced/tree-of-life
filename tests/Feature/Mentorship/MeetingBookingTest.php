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

test('the entrepreneur meetings page lists bookable slots and upcoming meetings', function () {
    [$entrepreneur, , $slot] = pairedWithSlot();

    $this->actingAs($entrepreneur)->get('/entrepreneur/meetings')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('entrepreneur/Meetings')
            ->has('bookable', 1)
            ->has('upcoming', 0));

    $this->actingAs($entrepreneur)->post('/entrepreneur/meetings', ['slot_id' => $slot->id]);

    $this->actingAs($entrepreneur)->get('/entrepreneur/meetings')
        ->assertInertia(fn (Assert $page) => $page->has('upcoming', 1));
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
