<?php

use App\Models\User;

test('a draft entrepreneur can save profile fields incrementally', function () {
    $user = User::factory()->entrepreneur()->draft()->create();

    $this->actingAs($user)->patch('/entrepreneur/profile', [
        'business_name' => 'Acme Textiles',
        'sector' => ['manufacturing', 'retail'],
    ])->assertRedirect();

    expect($user->entrepreneurProfile->fresh())
        ->business_name->toBe('Acme Textiles')
        ->and($user->entrepreneurProfile->fresh()->sector)->toContain('manufacturing');
});

test('the business email must be a unique, valid email', function () {
    $existing = User::factory()->entrepreneur()->create();
    $existing->entrepreneurProfile()->update(['business_email' => 'taken@biz.com']);

    $user = User::factory()->entrepreneur()->draft()->create();

    $this->actingAs($user)->from('/entrepreneur/onboarding')
        ->patch('/entrepreneur/profile', ['business_email' => 'taken@biz.com'])
        ->assertSessionHasErrors('business_email');

    $this->actingAs($user)->from('/entrepreneur/onboarding')
        ->patch('/entrepreneur/profile', ['business_email' => 'not-an-email'])
        ->assertSessionHasErrors('business_email');
});

test('the business phone must be unique and differ from the personal phone', function () {
    $user = User::factory()->entrepreneur()->draft()->create(['phone_number' => '+254700000001']);

    $this->actingAs($user)->from('/entrepreneur/onboarding')
        ->patch('/entrepreneur/profile', ['business_phone_number' => '+254700000001'])
        ->assertSessionHasErrors('business_phone_number');
});

test('operating-history numbers must be non-negative integers', function (array $payload) {
    $user = User::factory()->entrepreneur()->draft()->create();

    $this->actingAs($user)->from('/entrepreneur/onboarding')
        ->patch('/entrepreneur/profile', $payload)
        ->assertSessionHasErrors();
})->with([
    'negative years' => [['years_in_operation' => -1]],
    'non-numeric employees' => [['employee_count' => 'lots']],
]);

test('a mentor cannot edit an entrepreneur profile', function () {
    $mentor = User::factory()->mentor()->draft()->create();

    $this->actingAs($mentor)
        ->patch('/entrepreneur/profile', ['business_name' => 'Not Mine'])
        ->assertForbidden();
});
