<?php

use Illuminate\Support\Facades\Mail;

/**
 * Production sends through Resend. The transport is resolved lazily on the
 * first send, so a missing driver package surfaces as a failed invitation
 * email in production rather than at deploy time.
 */
test('the resend mailer used in production can be resolved', function () {
    config([
        'services.resend.key' => 're_test_key',
        'mail.mailers.resend' => ['transport' => 'resend'],
    ]);

    $transport = Mail::mailer('resend')->getSymfonyTransport();

    expect($transport::class)->toContain('Resend');
});
