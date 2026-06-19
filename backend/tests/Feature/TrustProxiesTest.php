<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;

/**
 * Production terminates TLS at nginx and forwards to PHP-FPM over plain
 * FastCGI, conveying the original scheme via X-Forwarded-Proto (see
 * nginx/templates/conf.d/*.template). bootstrap/app.php must trust that
 * header or Laravel treats every request as insecure — and with
 * SESSION_SECURE_COOKIE=true (set only in docker-compose.prod.yml) it then
 * emits http:// redirects and drops the session cookie, looping admin and
 * Sanctum logins. This path is never exercised by local-prod or e2e (neither
 * sets the secure-cookie flag), so lock the behaviour here.
 */
test('forwarded https requests are treated as secure behind the reverse proxy', function (): void {
    $request = Request::create(
        'http://'.config('app.primary_domain').'/up',
        'GET',
        server: [
            'REMOTE_ADDR' => '203.0.113.9',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ],
    );

    // Resolve the application's configured TrustProxies middleware (the
    // trustProxies(at: '*') call in bootstrap/app.php sets its static proxy
    // list at boot) and run the forwarded request through it.
    app(TrustProxies::class)->handle($request, fn (Request $r): mixed => null);

    expect($request->isSecure())->toBeTrue()
        ->and($request->getScheme())->toBe('https');
});

test('bootstrap configures trusted proxies', function (): void {
    // Source guard: a behavioural regression (someone removing the call) is
    // also caught above, but this pins the intent at the config site itself.
    expect(file_get_contents(base_path('bootstrap/app.php')))
        ->toContain('trustProxies');
});
