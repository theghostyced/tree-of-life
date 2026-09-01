<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Invitation Expiry Window
    |--------------------------------------------------------------------------
    |
    | How many days an invitation link stays valid for. Every path that issues
    | or re-issues a token reads this one value, so a resend can never grant a
    | different window than the original invite.
    |
    | Note the clock starts when the invitation row is created, not when the
    | email is delivered, so a queue backlog eats into the window.
    |
    */

    'expiry_days' => (int) env('INVITATION_EXPIRY_DAYS', 7),

];
