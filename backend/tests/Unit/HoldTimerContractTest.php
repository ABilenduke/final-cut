<?php

use App\Console\Commands\ExpireHeldBookings;

/**
 * Backend half of the cross-stack hold-timer contract (admin-v3 Plan 04).
 * The frontend promises seats for 8 minutes (useCart SESSION_HOLD_MINUTES);
 * the sweeper must keep a safety margin above that so in-flight checkouts
 * are never garbage-collected mid-payment. The frontend half lives in
 * frontend/tests/architecture/hold-timer-alignment.test.ts — change either
 * value and the matching pin fails, pointing you here.
 */
const HOLD_CONTRACT_MINUTES = 8;

test('the expire-held default keeps at least 2 minutes of margin above the frontend hold window', function (): void {
    $command = new ExpireHeldBookings;

    $default = (int) $command->getDefinition()->getOption('minutes')->getDefault();

    expect($default)->toBeGreaterThanOrEqual(HOLD_CONTRACT_MINUTES + 2)
        ->and($default)->toBe(20);
});
