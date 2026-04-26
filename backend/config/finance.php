<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Finance team notification email
    |--------------------------------------------------------------------------
    |
    | Recipient for admin-triggered finance events (gift card voids, refund
    | escalations, etc.). Dev points at Mailpit; in prod set
    | `FINANCE_NOTIFICATION_EMAIL` to an internal distribution list with
    | appropriate access controls — the emails include PII (gift card
    | recipient email + sender name + balance).
    |
    */

    'notification_email' => env('FINANCE_NOTIFICATION_EMAIL', 'finance@finalcut.test'),
];
