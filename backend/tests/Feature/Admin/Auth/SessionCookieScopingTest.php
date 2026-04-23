<?php

use Illuminate\Support\Facades\Config;

/**
 * SESSION_DRIVER=array under phpunit.xml — these tests assert that the
 * ScopeAdminSession middleware rewrites session.cookie / session.domain /
 * session.connection to the admin-scoped values on admin-host requests, and
 * leaves them alone on customer-host requests. The actual Redis DB isolation
 * (DB 2 vs DB 3) is exercised manually in dev (see progress journal); the
 * config rewrite is the only piece we can verify deterministically inside a
 * test that does not touch a live Redis instance.
 */
beforeEach(function (): void {
    $this->adminHost = config('filament.admin_domain');
    $this->primaryHost = config('app.primary_domain');
});

test('admin host request applies the admin session config via ScopeAdminSession', function (): void {
    $this->get("http://{$this->adminHost}/login");

    expect(config('session.cookie'))->toBe(config('filament.admin_session.cookie'));
    expect(config('session.domain'))->toBe(config('filament.admin_session.domain'));
    expect(config('session.connection'))->toBe(config('filament.admin_session.connection'));
});

test('primary host request does not get the admin session rewrite', function (): void {
    // Snapshot the framework defaults before any admin-host request mutates them.
    $defaultCookie = config('session.cookie');

    // Use a customer api endpoint mapped on the primary domain so the request
    // resolves through routes/api.php (no admin middleware in its stack).
    $this->getJson("http://{$this->primaryHost}/api/movies");

    expect(config('session.cookie'))->toBe($defaultCookie);
    expect(config('session.cookie'))->not->toBe(config('filament.admin_session.cookie'));
});

test('overriding ADMIN_SESSION_DOMAIN propagates through to session.domain', function (): void {
    Config::set('filament.admin_session.domain', 'admin.finalcut.example');

    $this->get("http://{$this->adminHost}/login");

    expect(config('session.domain'))->toBe('admin.finalcut.example');
});
