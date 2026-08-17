<?php

use App\Enums\InvitationImportStatus;
use App\Mail\UserInvitationMail;
use App\Models\InvitationImport;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * The admin invitation form was hardened first; these cover the other two paths
 * that put invitation mail on the queue, so a broker outage degrades the same
 * way everywhere instead of 500ing in two places and not the third.
 */
test('an entrepreneur inviting an employee still gets the mail out when the broker is down', function () {
    $entrepreneur = completeEntrepreneur();

    Mail::shouldReceive('to')->andReturnSelf();
    Mail::shouldReceive('queue')->once()->andThrow(new RedisException('Connection refused'));
    Mail::shouldReceive('sendNow')->once()->with(Mockery::type(UserInvitationMail::class));

    $this->actingAs($entrepreneur)
        ->post('/entrepreneur/employees', [
            'email' => 'employee@example.com',
            'name' => 'New Employee',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(UserInvitation::firstWhere('email', 'employee@example.com'))
        ->not->toBeNull()
        ->delivered_at->not->toBeNull();
});

test('starting a CSV import reports a queue outage instead of throwing at the admin', function () {
    $admin = User::factory()->admin()->approved()->create();

    Queue::shouldReceive('connection')->andReturnSelf();
    Queue::shouldReceive('pushOn', 'push')->andThrow(new RedisException('Connection refused'));

    $csv = UploadedFile::fake()->createWithContent(
        'invites.csv',
        "email,role,name\nrow@example.com,entrepreneur,Row Person\n",
    );

    $this->actingAs($admin)
        ->post('/admin/invitations/import', ['file' => $csv])
        ->assertRedirect();

    $import = InvitationImport::latest('id')->first();

    expect($import)->not->toBeNull()
        ->and($import->status)->toBe(InvitationImportStatus::Failed);
});
