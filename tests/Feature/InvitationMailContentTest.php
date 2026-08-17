<?php

use App\Mail\UserInvitationMail;
use App\Models\UserInvitation;
use Illuminate\Support\Facades\Mail;

/**
 * Gmail and Outlook block remote images by default, so a linked logo renders as
 * an empty box until the reader opts in. The mark has to travel with the
 * message.
 */
test('the invitation email embeds the logo rather than linking to it', function () {
    config(['mail.default' => 'array']);
    $invitation = UserInvitation::factory()->create(['email' => 'invitee@example.com']);

    Mail::to($invitation->email)->sendNow(new UserInvitationMail($invitation, 'raw-token'));

    $html = Mail::mailer('array')->getSymfonyTransport()->messages()[0]
        ->getOriginalMessage()
        ->getHtmlBody();

    expect($html)
        ->toContain('cid:')
        ->not->toContain('/images/brand/tree-of-life.png');
});

test('the invitation email carries a working accept link', function () {
    config(['mail.default' => 'array']);
    $invitation = UserInvitation::factory()->create(['email' => 'invitee@example.com']);

    Mail::to($invitation->email)->sendNow(new UserInvitationMail($invitation, 'raw-token'));

    $html = Mail::mailer('array')->getSymfonyTransport()->messages()[0]
        ->getOriginalMessage()
        ->getHtmlBody();

    expect($html)->toContain(url('/invitations/accept/raw-token'));
});
