<?php

use App\Enums\MeetingStatus;
use App\Enums\RescheduleStatus;
use App\Models\Meeting;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;

beforeEach(function () {
    $this->pairing = Pairing::factory()->create();
    $this->mentor = $this->pairing->mentor;
    $this->entrepreneur = $this->pairing->entrepreneur;

    $this->slot = MentorAvailabilitySlot::factory()->create([
        'mentor_user_id' => $this->mentor->id,
        'day_of_week' => 1,
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    $this->meeting = Meeting::factory()->create([
        'pairing_id' => $this->pairing->id,
        'mentor_availability_slot_id' => $this->slot->id,
        'status' => MeetingStatus::Confirmed,
        'starts_at' => now()->addWeeks(3),
        'ends_at' => now()->addWeeks(3)->addHour(),
    ]);
});

it('walks an entrepreneur through proposing a new time', function () {
    $this->actingAs($this->entrepreneur);

    $page = visit('/entrepreneur/meetings')
        ->assertPresent('html[data-hydrated=true]')
        ->assertNotPresent('dialog[open]');

    $page->click("@reschedule-meeting-{$this->meeting->id}")
        ->assertPresent('dialog[open]')
        ->assertSee('Move this call')
        ->assertSee('Why are you moving it?')
        // Nothing chosen yet, so the request cannot be sent.
        ->assertDisabled('@reschedule-submit');

    $page->click('@reschedule-option')
        ->type('@reschedule-reason', 'A supplier visit clashes with this slot.')
        ->assertEnabled('@reschedule-submit')
        ->click('@reschedule-submit');

    $page->assertSee('Reschedule requested, waiting on your mentor.')
        ->assertNoJavaScriptErrors();

    $reschedule = $this->meeting->reschedules()->sole();

    expect($reschedule->status)->toBe(RescheduleStatus::Pending)
        ->and($reschedule->requested_by_user_id)->toBe($this->entrepreneur->id)
        ->and($this->meeting->fresh()->starts_at->equalTo($reschedule->previous_starts_at))->toBeTrue();
});

it('lets a mentor move a call without asking permission', function () {
    $this->actingAs($this->mentor);

    $page = visit('/mentor/meetings')
        ->assertPresent('html[data-hydrated=true]');

    $page->click("@reschedule-meeting-{$this->meeting->id}")
        ->assertPresent('dialog[open]')
        ->assertSee('Move this call')
        // Optional for the mentor: their counterparty gets told, not asked.
        ->assertSee('Reason (optional)')
        ->click('@reschedule-option')
        ->assertEnabled('@reschedule-submit')
        ->click('@reschedule-submit')
        ->assertNoJavaScriptErrors();

    $reschedule = $this->meeting->reschedules()->sole();

    expect($reschedule->status)->toBe(RescheduleStatus::Accepted)
        ->and($this->meeting->fresh()->starts_at->equalTo($reschedule->new_starts_at))->toBeTrue();
});

it('offers no reschedule action once a request is already pending', function () {
    $this->actingAs($this->entrepreneur);

    visit('/entrepreneur/meetings')
        ->assertPresent('html[data-hydrated=true]')
        ->click("@reschedule-meeting-{$this->meeting->id}")
        ->click('@reschedule-option')
        ->type('@reschedule-reason', 'Clashes.')
        ->click('@reschedule-submit')
        ->assertSee('Reschedule requested, waiting on your mentor.')
        ->assertNotPresent("@reschedule-meeting-{$this->meeting->id}")
        ->assertNoJavaScriptErrors();
});
