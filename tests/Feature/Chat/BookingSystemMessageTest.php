<?php

use App\Actions\Mentorship\BookMeeting;
use App\Enums\MessageType;
use App\Models\MentorAvailabilitySlot;
use App\Models\Pairing;

test('booking a meeting posts a system message into the conversation', function () {
    $entrepreneur = completeEntrepreneur();
    $mentor = availableMentor();
    $pairing = Pairing::create([
        'entrepreneur_user_id' => $entrepreneur->id,
        'mentor_user_id' => $mentor->id,
    ]);
    $slot = MentorAvailabilitySlot::factory()->for($mentor, 'mentor')->create([
        'day_of_week' => 2, 'start_time' => '09:00', 'end_time' => '10:00', 'is_active' => true,
    ]);

    app(BookMeeting::class)->handle($pairing, $slot);

    $conversation = $pairing->conversation()->firstOrFail();
    $system = $conversation->messages()->where('type', MessageType::System)->first();
    expect($system)->not->toBeNull()
        ->and($system->body)->toContain('Call scheduled');
});
