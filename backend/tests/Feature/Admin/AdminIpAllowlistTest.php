<?php

use App\Http\Middleware\AdminIpAllowlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * The middleware bypasses entirely in `local` and `testing` environments
 * (so dev work and the rest of the test suite are unaffected). To exercise
 * the allowlist branches, each test forces the env to `production` first.
 */
function runIpAllowlistMiddleware(string $clientIp): void
{
    $middleware = new AdminIpAllowlist;
    $request = Request::create('/admin', 'GET');
    $request->server->set('REMOTE_ADDR', $clientIp);

    $middleware->handle($request, fn () => response('ok'));
}

beforeEach(function (): void {
    app()->detectEnvironment(fn () => 'production');
});

afterEach(function (): void {
    // Restore testing env for the rest of the suite — Pest preserves this
    // between tests via shared application state.
    app()->detectEnvironment(fn () => 'testing');
});

test('a client IP inside an allowed CIDR passes through', function (): void {
    config()->set('admin.ip_allowlist', '203.0.113.0/24, 198.51.100.10');

    expect(fn () => runIpAllowlistMiddleware('203.0.113.42'))->not->toThrow(HttpException::class);
});

test('a client IP that exactly matches a /32 host passes through', function (): void {
    config()->set('admin.ip_allowlist', '198.51.100.10/32');

    expect(fn () => runIpAllowlistMiddleware('198.51.100.10'))->not->toThrow(HttpException::class);
});

test('a client IP outside the allowlist returns 403', function (): void {
    Log::spy();
    config()->set('admin.ip_allowlist', '203.0.113.0/24');

    expect(fn () => runIpAllowlistMiddleware('192.0.2.5'))
        ->toThrow(HttpException::class, 'Access denied');

    Log::shouldHaveReceived('info')
        ->withArgs(fn ($message) => str_contains($message, 'rejected IP'));
});

test('an empty allowlist fails closed and logs error-level', function (): void {
    Log::spy();
    config()->set('admin.ip_allowlist', '');
    config()->set('admin.ip_allowlist_emergency_open', false);

    expect(fn () => runIpAllowlistMiddleware('203.0.113.10'))
        ->toThrow(HttpException::class, 'Access denied');

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($message) => str_contains($message, 'no allowlist configured'));
});

test('emergency-open allows requests with empty allowlist and logs error-level', function (): void {
    Log::spy();
    config()->set('admin.ip_allowlist', '');
    config()->set('admin.ip_allowlist_emergency_open', true);

    expect(fn () => runIpAllowlistMiddleware('203.0.113.10'))->not->toThrow(HttpException::class);

    Log::shouldHaveReceived('error')
        ->withArgs(fn ($message) => str_contains($message, 'EMERGENCY_OPEN'));
});

test('IPv6 requests are rejected outright with a logged warning', function (): void {
    Log::spy();
    config()->set('admin.ip_allowlist', '203.0.113.0/24');

    expect(fn () => runIpAllowlistMiddleware('2001:db8::1'))
        ->toThrow(HttpException::class, 'Access denied');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => str_contains($message, 'IPv6 request rejected'));
});

test('malformed CIDR entries are skipped with a logged warning and do not crash', function (): void {
    Log::spy();
    // Mix of malformed and valid entries — the valid one must still match.
    config()->set('admin.ip_allowlist', 'not-a-cidr, 203.0.113.0/99, 198.51.100.10');

    expect(fn () => runIpAllowlistMiddleware('198.51.100.10'))->not->toThrow(HttpException::class);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn ($message) => str_contains($message, 'malformed CIDR'));
});

test('a /0 CIDR matches every IPv4 address', function (): void {
    config()->set('admin.ip_allowlist', '0.0.0.0/0');

    expect(fn () => runIpAllowlistMiddleware('192.0.2.99'))->not->toThrow(HttpException::class);
    expect(fn () => runIpAllowlistMiddleware('203.0.113.1'))->not->toThrow(HttpException::class);
});

test('local environment bypasses the allowlist entirely', function (): void {
    app()->detectEnvironment(fn () => 'local');
    config()->set('admin.ip_allowlist', ''); // Empty would normally fail closed in prod.

    expect(fn () => runIpAllowlistMiddleware('203.0.113.99'))->not->toThrow(HttpException::class);
});

test('whitespace-padded CIDR entries are trimmed before matching', function (): void {
    config()->set('admin.ip_allowlist', '  203.0.113.0/24  ,  198.51.100.10  ');

    expect(fn () => runIpAllowlistMiddleware('203.0.113.50'))->not->toThrow(HttpException::class);
    expect(fn () => runIpAllowlistMiddleware('198.51.100.10'))->not->toThrow(HttpException::class);
});
