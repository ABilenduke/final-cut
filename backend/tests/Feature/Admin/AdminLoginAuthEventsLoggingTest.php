<?php

use Illuminate\Auth\Events\Failed;
use Illuminate\Support\Facades\File;

/**
 * The `admin_auth_events` Monolog channel and the Fail2ban filter at
 * fail2ban/filter.d/admin-login.conf form a tightly-coupled contract:
 * the JSON shape Monolog emits MUST match the regex Fail2ban scans.
 *
 * This test asserts the application side of the contract — every required
 * field is present in the dedicated log, and the channel routes through
 * the JsonFormatter (single line per event, no trailing log noise).
 *
 * The regex side is verified by the CI step `fail2ban-regex` regeneration
 * (.github/workflows/backend-tests.yml), which dispatches the `Failed`
 * auth event via `php artisan tinker --execute …` and runs the
 * freshly-written line through the committed filter.
 */
test('failed admin login writes the JsonFormatter contract to admin_auth_events', function (): void {
    // Point the channel at a tmpfile so the assertion is deterministic
    // and we don't depend on the dev log file's prior contents.
    $tmpPath = tempnam(sys_get_temp_dir(), 'admin-auth-events-').'.log';
    config()->set('logging.channels.admin_auth_events.path', $tmpPath);

    event(new Failed('admin', null, [
        'email' => 'attacker@example.com',
        'password' => 'wrong',
    ]));

    $contents = File::get($tmpPath);
    File::delete($tmpPath);

    $line = trim($contents);
    expect($line)->not->toBe('');

    $decoded = json_decode($line, true);
    expect($decoded)->toBeArray()
        ->and($decoded['message'])->toBe('Failed admin login')
        ->and($decoded['context']['email'])->toBe('attacker@example.com')
        ->and($decoded['context']['ip'])->toBeString()
        ->and($decoded['level_name'])->toBe('INFO')
        ->and($decoded)->toHaveKey('datetime');
});

test('non-admin guard failures do not pollute the admin auth events log', function (): void {
    $tmpPath = tempnam(sys_get_temp_dir(), 'admin-auth-events-').'.log';
    config()->set('logging.channels.admin_auth_events.path', $tmpPath);

    event(new Failed('web', null, [
        'email' => 'customer@example.com',
        'password' => 'wrong',
    ]));

    $contents = File::exists($tmpPath) ? File::get($tmpPath) : '';
    File::delete($tmpPath);

    expect(trim($contents))->toBe('');
});
