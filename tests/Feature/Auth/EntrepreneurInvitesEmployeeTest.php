<?php

use App\Enums\UserRole;
use App\Mail\UserInvitationMail;
use App\Models\Company;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

test('an approved entrepreneur can invite an employee to their own company', function () {
    Mail::fake();
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    $company = Company::factory()->create(['owner_id' => $entrepreneur->id]);

    $this->actingAs($entrepreneur)
        ->post('/entrepreneur/employees', [
            'email' => 'teammate@example.com',
            'name' => 'Team Mate',
        ])->assertRedirect();

    $invitation = UserInvitation::firstWhere('email', 'teammate@example.com');

    expect($invitation)->not->toBeNull()
        ->and($invitation->role)->toBe(UserRole::Employee)
        ->and($invitation->invited_by)->toBe($entrepreneur->id)
        ->and($invitation->company_id)->toBe($company->id);

    Mail::assertQueued(UserInvitationMail::class, fn ($mail) => $mail->hasTo('teammate@example.com'));
});

test('an entrepreneur cannot escalate by inviting a non-employee role', function (string $role) {
    Mail::fake();
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    Company::factory()->create(['owner_id' => $entrepreneur->id]);

    $this->actingAs($entrepreneur)
        ->from('/entrepreneur/employees')
        ->post('/entrepreneur/employees', [
            'email' => 'target@example.com',
            'role' => $role,
        ]);

    // Any role the request tries to smuggle in is ignored or rejected; if a row
    // is created at all it can only ever be an employee.
    $invitation = UserInvitation::firstWhere('email', 'target@example.com');
    expect($invitation?->role)->not->toBe(UserRole::from($role));
})->with(['admin', 'mentor', 'entrepreneur']);

test('a deactivated entrepreneur cannot invite employees', function () {
    $entrepreneur = User::factory()->entrepreneur()->deactivated()->create();

    $this->actingAs($entrepreneur)
        ->post('/entrepreneur/employees', ['email' => 'x@example.com'])
        ->assertForbidden();

    expect(UserInvitation::count())->toBe(0);
});

test('mentors and employees cannot invite employees', function (string $factory) {
    $user = User::factory()->{$factory}()->approved()->create();

    $this->actingAs($user)
        ->post('/entrepreneur/employees', ['email' => 'x@example.com'])
        ->assertForbidden();

    expect(UserInvitation::count())->toBe(0);
})->with(['mentor', 'employee']);

test('accepting an employee invitation scopes the account to the company', function () {
    $entrepreneur = User::factory()->entrepreneur()->approved()->create();
    $company = Company::factory()->create(['owner_id' => $entrepreneur->id]);

    $raw = Str::random(64);
    $invitation = UserInvitation::factory()->pending()->create([
        'email' => 'teammate@example.com',
        'role' => UserRole::Employee,
        'invited_by' => $entrepreneur->id,
        'company_id' => $company->id,
        'token_hash' => hash('sha256', $raw),
    ]);

    $this->post("/invitations/accept/{$raw}", [
        'name' => 'Team Mate',
        'password' => 'password-1234',
        'password_confirmation' => 'password-1234',
    ]);

    $employee = User::firstWhere('email', 'teammate@example.com');

    expect($employee->role)->toBe(UserRole::Employee)
        ->and($employee->company_id)->toBe($company->id);

    // Employees never inherit admin/entrepreneur-owner capabilities.
    $this->actingAs($employee)->get('/admin/dashboard')->assertForbidden();
    $this->actingAs($employee)->post('/entrepreneur/employees', ['email' => 'y@example.com'])
        ->assertForbidden();
});
