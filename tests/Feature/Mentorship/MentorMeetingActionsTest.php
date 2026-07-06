<?php

use App\Enums\RescheduleStatus;
use App\Models\Meeting;
use App\Models\MeetingReport;
use App\Models\MeetingReschedule;
use App\Models\Pairing;
use App\Models\User;

beforeEach(function () {
    $this->mentor = User::factory()->mentor()->approved()->create();
    $this->pairing = Pairing::factory()->create(['mentor_user_id' => $this->mentor->id]);
});

function pendingRescheduleFor(Pairing $pairing): MeetingReschedule
{
    $meeting = Meeting::factory()->create(['pairing_id' => $pairing->id]);

    return MeetingReschedule::factory()->create(['meeting_id' => $meeting->id]);
}

test('accepting a reschedule applies the proposed times', function () {
    $reschedule = pendingRescheduleFor($this->pairing);

    $this->actingAs($this->mentor)
        ->post("/mentor/reschedules/{$reschedule->id}/accept")
        ->assertRedirect();

    $reschedule->refresh();
    expect($reschedule->status)->toBe(RescheduleStatus::Accepted)
        ->and($reschedule->reviewed_by_user_id)->toBe($this->mentor->id)
        ->and($reschedule->reviewed_at)->not->toBeNull()
        ->and($reschedule->meeting->starts_at->equalTo($reschedule->new_starts_at))->toBeTrue()
        ->and($reschedule->meeting->ends_at->equalTo($reschedule->new_ends_at))->toBeTrue();
});

test('declining a reschedule stamps the review without moving the meeting', function () {
    $reschedule = pendingRescheduleFor($this->pairing);
    $original = $reschedule->meeting->starts_at;

    $this->actingAs($this->mentor)
        ->post("/mentor/reschedules/{$reschedule->id}/decline")
        ->assertRedirect();

    $reschedule->refresh();
    expect($reschedule->status)->toBe(RescheduleStatus::Declined)
        ->and($reschedule->meeting->starts_at->equalTo($original))->toBeTrue();
});

test('a reviewed reschedule cannot be reviewed again', function () {
    $reschedule = pendingRescheduleFor($this->pairing);
    $reschedule->update(['status' => RescheduleStatus::Declined]);

    $this->actingAs($this->mentor)
        ->post("/mentor/reschedules/{$reschedule->id}/accept")
        ->assertForbidden();
});

test('the requester cannot review their own reschedule', function () {
    $meeting = Meeting::factory()->create(['pairing_id' => $this->pairing->id]);
    $reschedule = MeetingReschedule::factory()->create([
        'meeting_id' => $meeting->id,
        'requested_by_user_id' => $this->mentor->id,
    ]);

    $this->actingAs($this->mentor)
        ->post("/mentor/reschedules/{$reschedule->id}/accept")
        ->assertForbidden();
});

test('a foreign mentor cannot review someone elses reschedule', function () {
    $reschedule = pendingRescheduleFor(Pairing::factory()->create());

    $this->actingAs($this->mentor)
        ->post("/mentor/reschedules/{$reschedule->id}/accept")
        ->assertForbidden();
});

test('a mentor reports on their completed meeting', function () {
    $meeting = Meeting::factory()->completed()->create(['pairing_id' => $this->pairing->id]);

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$meeting->id}/report", ['summary' => 'We refined the pitch.'])
        ->assertRedirect();

    expect(MeetingReport::sole())
        ->summary->toBe('We refined the pitch.')
        ->submitted_by_user_id->toBe($this->mentor->id);
});

test('reports are refused for meetings that are not completed', function () {
    $meeting = Meeting::factory()->create(['pairing_id' => $this->pairing->id]);

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$meeting->id}/report", ['summary' => 'Too early.'])
        ->assertForbidden();
});

test('a second report on the same meeting is refused', function () {
    $meeting = Meeting::factory()->completed()->create(['pairing_id' => $this->pairing->id]);
    MeetingReport::factory()->create(['meeting_id' => $meeting->id]);

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$meeting->id}/report", ['summary' => 'Again.'])
        ->assertForbidden();
});

test('report summaries are required and bounded', function () {
    $meeting = Meeting::factory()->completed()->create(['pairing_id' => $this->pairing->id]);

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$meeting->id}/report", ['summary' => ''])
        ->assertSessionHasErrors('summary');

    $this->actingAs($this->mentor)
        ->post("/mentor/meetings/{$meeting->id}/report", ['summary' => str_repeat('a', 5001)])
        ->assertSessionHasErrors('summary');
});

test('entrepreneurs and admins get 403s on the mentor endpoints', function () {
    $meeting = Meeting::factory()->completed()->create(['pairing_id' => $this->pairing->id]);
    $reschedule = pendingRescheduleFor($this->pairing);

    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    $admin = User::factory()->admin()->approved()->create();

    foreach ([$entrepreneur, $admin] as $user) {
        $this->actingAs($user)->post("/mentor/meetings/{$meeting->id}/report", ['summary' => 'x'])->assertForbidden();
        $this->actingAs($user)->post("/mentor/reschedules/{$reschedule->id}/accept")->assertForbidden();
    }
});

test('a stale reschedule instance cannot be reviewed twice', function () {
    $reschedule = pendingRescheduleFor($this->pairing);
    $stale = App\Models\MeetingReschedule::find($reschedule->id);

    app(App\Actions\Mentorship\ReviewMeetingReschedule::class)
        ->handle($reschedule, $this->mentor, accept: true);

    app(App\Actions\Mentorship\ReviewMeetingReschedule::class)
        ->handle($stale, $this->mentor, accept: false);
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);

test('a duplicate report submission aborts instead of erroring', function () {
    $meeting = App\Models\Meeting::factory()->completed()->create(['pairing_id' => $this->pairing->id]);
    App\Models\MeetingReport::factory()->create(['meeting_id' => $meeting->id]);

    app(App\Actions\Mentorship\SubmitMeetingReport::class)
        ->handle($meeting, $this->mentor, 'duplicate');
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);
