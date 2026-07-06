<?php

use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MeetingReschedule;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->mentor = User::factory()->mentor()->approved()->create();
});

function pairingFor(User $mentor): Pairing
{
    return Pairing::factory()->create(['mentor_user_id' => $mentor->id]);
}

test('the dashboard shares attention items for pending reschedules and missing reports', function () {
    $pairing = pairingFor($this->mentor);
    $meeting = Meeting::factory()->create(['pairing_id' => $pairing->id]);
    MeetingReschedule::factory()->create(['meeting_id' => $meeting->id]);

    $done = Meeting::factory()->completed()->create(['pairing_id' => $pairing->id]);

    $reported = Meeting::factory()->completed()->create(['pairing_id' => $pairing->id]);
    MeetingReport::factory()->create(['meeting_id' => $reported->id]);

    $this->actingAs($this->mentor)
        ->get('/mentor/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->component('mentor/Dashboard')
            ->count('attention.reschedules', 1)
            ->count('attention.missingReports', 1)
            ->where('attention.missingReports.0.meetingId', $done->id));
});

test('reschedules the mentor requested themselves are not attention items', function () {
    $pairing = pairingFor($this->mentor);
    $meeting = Meeting::factory()->create(['pairing_id' => $pairing->id]);
    MeetingReschedule::factory()->create([
        'meeting_id' => $meeting->id,
        'requested_by_user_id' => $this->mentor->id,
    ]);

    $this->actingAs($this->mentor)
        ->get('/mentor/dashboard')
        ->assertInertia(fn (Assert $page) => $page->count('attention.reschedules', 0));
});

test('the week list holds only confirmed meetings in the next seven days', function () {
    $pairing = pairingFor($this->mentor);
    Meeting::factory()->create(['pairing_id' => $pairing->id]); // tomorrow
    Meeting::factory()->create([
        'pairing_id' => $pairing->id,
        'starts_at' => now()->addDays(9),
        'ends_at' => now()->addDays(9)->addHour(),
    ]);
    Meeting::factory()->cancelled()->create(['pairing_id' => $pairing->id]);

    $this->actingAs($this->mentor)
        ->get('/mentor/dashboard')
        ->assertInertia(fn (Assert $page) => $page->count('meetings', 1));
});

test('mentees, availability, and stats are shaped', function () {
    $pairing = pairingFor($this->mentor);
    Meeting::factory()->completed()->create(['pairing_id' => $pairing->id]);
    MentorAvailabilitySlot::factory()->create(['mentor_user_id' => $this->mentor->id]);
    MentorAvailabilitySlot::factory()->create([
        'mentor_user_id' => $this->mentor->id,
        'is_active' => false,
    ]);

    $this->actingAs($this->mentor)
        ->get('/mentor/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->count('mentees', 1)
            ->where('mentees.0.name', $pairing->entrepreneur->name)
            ->where('availability.activeCount', 1)
            ->count('availability.slots', 1)
            ->where('stats.menteeCount', 1)
            ->where('stats.completedCount', 1)
            ->where('stats.hoursMentored', 1));
});

test('another mentors data never leaks in', function () {
    $other = Pairing::factory()->create();
    Meeting::factory()->create(['pairing_id' => $other->id]);

    $this->actingAs($this->mentor)
        ->get('/mentor/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->count('meetings', 0)
            ->count('mentees', 0));
});
