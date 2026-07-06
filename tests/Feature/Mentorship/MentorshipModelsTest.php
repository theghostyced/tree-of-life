<?php

use App\Enums\MeetingStatus;
use App\Enums\PairingStatus;
use App\Enums\RescheduleStatus;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MeetingReschedule;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Models\User;
use Illuminate\Database\QueryException;

test('a pairing links a mentor and an entrepreneur', function () {
    $pairing = Pairing::factory()->create();

    expect($pairing->status)->toBe(PairingStatus::Active)
        ->and($pairing->mentor->isMentor())->toBeTrue()
        ->and($pairing->entrepreneur->isEntrepreneur())->toBeTrue();
});

test('a meeting belongs to a pairing and reaches its people through it', function () {
    $meeting = Meeting::factory()->create();

    expect($meeting->status)->toBe(MeetingStatus::Confirmed)
        ->and($meeting->pairing)->toBeInstanceOf(Pairing::class)
        ->and($meeting->pairing->mentor)->toBeInstanceOf(User::class)
        ->and($meeting->durationMinutes())->toBe(60);
});

test('a completed meeting can have exactly one report', function () {
    $report = MeetingReport::factory()->create();

    expect($report->meeting->status)->toBe(MeetingStatus::Completed)
        ->and($report->meeting->report->is($report))->toBeTrue();

    MeetingReport::factory()->create(['meeting_id' => $report->meeting_id]);
})->throws(QueryException::class);

test('a reschedule tracks proposed times and its requester', function () {
    $reschedule = MeetingReschedule::factory()->create();

    expect($reschedule->status)->toBe(RescheduleStatus::Pending)
        ->and($reschedule->requestedBy->is($reschedule->meeting->pairing->entrepreneur))->toBeTrue()
        ->and($reschedule->new_starts_at->gt($reschedule->previous_starts_at))->toBeTrue();
});

test('availability slots belong to a mentor and cast is_active', function () {
    $slot = MentorAvailabilitySlot::factory()->create();

    expect($slot->mentor->isMentor())->toBeTrue()
        ->and($slot->is_active)->toBeTrue()
        ->and($slot->day_of_week)->toBeInt();
});
