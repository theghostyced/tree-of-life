<?php

use App\Actions\Mentorship\BookMeeting;
use App\Enums\MeetingStatus;
use App\Enums\MessageType;
use App\Enums\RescheduleStatus;
use App\Models\Meeting;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Models\User;
use App\Notifications\MeetingRescheduled;
use App\Notifications\MeetingRescheduleRequested;
use App\Notifications\MeetingRescheduleReviewed;
use Illuminate\Support\Facades\Notification;

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

/** The next time this pairing could actually move to. */
function freeOccurrence(MentorAvailabilitySlot $slot, Pairing $pairing): int
{
    return BookMeeting::freeOccurrences($slot, $pairing)->first()->getTimestampMs();
}

test('an entrepreneur proposes a new time and the meeting does not move yet', function () {
    Notification::fake();
    $originalStart = $this->meeting->starts_at;

    $this->actingAs($this->entrepreneur)
        ->post("/entrepreneur/meetings/{$this->meeting->id}/reschedule", [
            'slot_id' => $this->slot->id,
            'starts_at' => freeOccurrence($this->slot, $this->pairing),
            'reason' => 'A supplier visit clashes with this slot.',
        ])->assertRedirect();

    $reschedule = $this->meeting->reschedules()->sole();

    expect($reschedule->status)->toBe(RescheduleStatus::Pending)
        ->and($reschedule->requested_by_user_id)->toBe($this->entrepreneur->id)
        ->and($reschedule->reviewed_at)->toBeNull()
        ->and($reschedule->reason)->toBe('A supplier visit clashes with this slot.')
        // Untouched until the mentor accepts.
        ->and($this->meeting->fresh()->starts_at->equalTo($originalStart))->toBeTrue();

    Notification::assertSentTo($this->mentor, MeetingRescheduleRequested::class);
    Notification::assertNotSentTo($this->entrepreneur, MeetingRescheduleRequested::class);
});

test('a mentor moves the call immediately and the entrepreneur is told', function () {
    Notification::fake();
    $newStart = freeOccurrence($this->slot, $this->pairing);

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$this->meeting->id}/reschedule", [
            'slot_id' => $this->slot->id,
            'starts_at' => $newStart,
        ])->assertRedirect();

    $reschedule = $this->meeting->reschedules()->sole();

    expect($reschedule->status)->toBe(RescheduleStatus::Accepted)
        ->and($reschedule->requested_by_user_id)->toBe($this->mentor->id)
        ->and($reschedule->reviewed_by_user_id)->toBe($this->mentor->id)
        ->and($reschedule->reviewed_at)->not->toBeNull()
        ->and($this->meeting->fresh()->starts_at->getTimestampMs())->toBe($newStart);

    Notification::assertSentTo($this->entrepreneur, MeetingRescheduled::class);
    Notification::assertNotSentTo($this->mentor, MeetingRescheduled::class);
});

test('a mentor move is announced in the pairing chat', function () {
    Notification::fake();

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$this->meeting->id}/reschedule", [
            'slot_id' => $this->slot->id,
            'starts_at' => freeOccurrence($this->slot, $this->pairing),
        ])->assertRedirect();

    $conversation = $this->pairing->conversation()->first();

    expect($conversation)->not->toBeNull()
        ->and($conversation->messages()->where('type', MessageType::System)->latest('id')->first()->body)
        ->toContain('Call moved to');
});

test('the previous times are recorded so the change is auditable', function () {
    Notification::fake();
    $before = $this->meeting->starts_at;

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$this->meeting->id}/reschedule", [
            'slot_id' => $this->slot->id,
            'starts_at' => freeOccurrence($this->slot, $this->pairing),
        ]);

    expect($this->meeting->reschedules()->sole()->previous_starts_at->equalTo($before))->toBeTrue();
});

test('a time outside the mentor\'s published availability is refused', function () {
    Notification::fake();

    $this->actingAs($this->entrepreneur)
        ->post("/entrepreneur/meetings/{$this->meeting->id}/reschedule", [
            'slot_id' => $this->slot->id,
            'starts_at' => now()->addWeeks(2)->addMinutes(37)->getTimestampMs(),
            'reason' => 'Any time really.',
        ])->assertStatus(422);

    expect($this->meeting->reschedules()->count())->toBe(0);
});

test('an entrepreneur must say why they are moving the call', function () {
    Notification::fake();

    $this->actingAs($this->entrepreneur)
        ->post("/entrepreneur/meetings/{$this->meeting->id}/reschedule", [
            'slot_id' => $this->slot->id,
            'starts_at' => freeOccurrence($this->slot, $this->pairing),
        ])->assertSessionHasErrors('reason');

    expect($this->meeting->reschedules()->count())->toBe(0);
});

test('an entrepreneur cannot stack a second pending request', function () {
    Notification::fake();
    $payload = [
        'slot_id' => $this->slot->id,
        'starts_at' => freeOccurrence($this->slot, $this->pairing),
        'reason' => 'Still clashes.',
    ];

    $this->actingAs($this->entrepreneur)
        ->post("/entrepreneur/meetings/{$this->meeting->id}/reschedule", $payload)
        ->assertRedirect();

    $this->actingAs($this->entrepreneur)
        ->post("/entrepreneur/meetings/{$this->meeting->id}/reschedule", $payload)
        ->assertForbidden();

    expect($this->meeting->reschedules()->count())->toBe(1);
});

test('a proposal the mentor accepts moves the call and reaches the chat', function () {
    Notification::fake();
    $newStart = freeOccurrence($this->slot, $this->pairing);

    $this->actingAs($this->entrepreneur)
        ->post("/entrepreneur/meetings/{$this->meeting->id}/reschedule", [
            'slot_id' => $this->slot->id,
            'starts_at' => $newStart,
            'reason' => 'Clashes with a supplier visit.',
        ])->assertRedirect();

    $reschedule = $this->meeting->reschedules()->sole();

    $this->actingAs($this->mentor)
        ->post("/mentor/reschedules/{$reschedule->id}/accept")
        ->assertRedirect();

    expect($reschedule->fresh()->status)->toBe(RescheduleStatus::Accepted)
        ->and($this->meeting->fresh()->starts_at->getTimestampMs())->toBe($newStart);

    $latest = $this->pairing->conversation()->first()
        ->messages()->where('type', MessageType::System)->latest('id')->first();

    expect($latest->body)->toContain('Call moved to');

    Notification::assertSentTo($this->entrepreneur, MeetingRescheduleReviewed::class);
});

test('someone outside the pairing cannot move the call', function () {
    Notification::fake();
    $stranger = User::factory()->mentor()->approved()->create();

    $this->actingAs($stranger)
        ->post("/mentor/meetings/{$this->meeting->id}/reschedule", [
            'slot_id' => $this->slot->id,
            'starts_at' => freeOccurrence($this->slot, $this->pairing),
        ])->assertForbidden();
});

test('a call that has already started cannot be moved', function () {
    Notification::fake();
    $this->meeting->update([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->subMinutes(30),
    ]);

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$this->meeting->id}/reschedule", [
            'slot_id' => $this->slot->id,
            'starts_at' => freeOccurrence($this->slot, $this->pairing),
        ])->assertForbidden();
});

test('a cancelled call cannot be moved', function () {
    Notification::fake();
    $this->meeting->update(['status' => MeetingStatus::Cancelled]);

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$this->meeting->id}/reschedule", [
            'slot_id' => $this->slot->id,
            'starts_at' => freeOccurrence($this->slot, $this->pairing),
        ])->assertForbidden();
});
