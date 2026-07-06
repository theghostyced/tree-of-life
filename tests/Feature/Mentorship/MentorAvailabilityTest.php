<?php

use App\Models\MentorAvailabilitySlot;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a mentor can add a virtual availability slot', function () {
    $mentor = User::factory()->mentor()->approved()->create();

    $this->actingAs($mentor)->post('/mentor/availability', [
        'day_of_week' => 2,
        'start_time' => '09:00',
        'end_time' => '10:30',
        'session_type' => 'virtual',
        'meeting_link' => 'https://meet.google.com/abc-defg-hij',
    ])->assertRedirect();

    expect(MentorAvailabilitySlot::where('mentor_user_id', $mentor->id)->count())->toBe(1);
});

test('the availability page lists the mentor’s own slots', function () {
    $mentor = User::factory()->mentor()->approved()->create();
    MentorAvailabilitySlot::factory()->for($mentor, 'mentor')->create(['day_of_week' => 1]);
    MentorAvailabilitySlot::factory()->create(); // another mentor's slot

    $this->actingAs($mentor)->get('/mentor/availability')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('mentor/Availability')
            ->has('slots', 1));
});

test('a slot requires the end time to be after the start time', function () {
    $mentor = User::factory()->mentor()->approved()->create();

    $this->actingAs($mentor)->from('/mentor/availability')
        ->post('/mentor/availability', [
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '09:00',
            'session_type' => 'virtual',
        ])->assertSessionHasErrors('end_time');
});

test('an in-person slot requires a location', function () {
    $mentor = User::factory()->mentor()->approved()->create();

    $this->actingAs($mentor)->from('/mentor/availability')
        ->post('/mentor/availability', [
            'day_of_week' => 1,
            'start_time' => '09:00',
            'end_time' => '10:00',
            'session_type' => 'in_person',
        ])->assertSessionHasErrors('location');
});

test('a mentor can remove their own slot', function () {
    $mentor = User::factory()->mentor()->approved()->create();
    $slot = MentorAvailabilitySlot::factory()->for($mentor, 'mentor')->create();

    $this->actingAs($mentor)->delete("/mentor/availability/{$slot->id}")->assertRedirect();

    expect(MentorAvailabilitySlot::find($slot->id))->toBeNull();
});

test('a mentor cannot remove another mentor’s slot', function () {
    $mentor = User::factory()->mentor()->approved()->create();
    $foreign = MentorAvailabilitySlot::factory()->create();

    $this->actingAs($mentor)->delete("/mentor/availability/{$foreign->id}")->assertForbidden();

    expect(MentorAvailabilitySlot::find($foreign->id))->not->toBeNull();
});
