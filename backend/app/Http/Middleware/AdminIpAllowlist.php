<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Layer-2 IP allowlist for the admin panel.
 *
 * The first defence is nginx (allow/deny block in admin.conf.template);
 * this middleware is the backstop that catches:
 *   - misconfigured nginx (envsubst gone wrong, hot-reload skipped)
 *   - direct container access during incident response
 *   - any future request path that bypasses the edge proxy
 *
 * Security posture: fail-closed by default. An empty allowlist in any
 * non-local environment denies every request at error-level. The
 * `ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN` env flag is the explicit, loud
 * escape hatch for the deploy chicken-and-egg.
 *
 * v1 is IPv4-only. IPv6 requests are rejected outright with a logged
 * warning so the v1 contract is observable rather than silently broken
 * if IPv6 ingress is ever added without updating this middleware, the
 * nginx block, and the Fail2ban jail in lockstep.
 */
class AdminIpAllowlist
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->environment('local', 'testing')) {
            return $next($request);
        }

        $clientIp = $request->ip();

        if ($clientIp !== null && str_contains($clientIp, ':')) {
            Log::warning('AdminIpAllowlist: IPv6 request rejected (v1 is IPv4-only)', [
                'ip' => $clientIp,
                'path' => $request->path(),
            ]);
            abort(403, 'Access denied');
        }

        $allowlist = array_filter(array_map(
            'trim',
            explode(',', (string) config('admin.ip_allowlist', '')),
        ));

        if (empty($allowlist)) {
            if (config('admin.ip_allowlist_emergency_open')) {
                Log::error('AdminIpAllowlist: EMERGENCY_OPEN flag active — allowing all IPs', [
                    'ip' => $clientIp,
                    'path' => $request->path(),
                ]);

                return $next($request);
            }

            Log::error('AdminIpAllowlist: no allowlist configured and EMERGENCY_OPEN not set — denying request', [
                'ip' => $clientIp,
                'path' => $request->path(),
            ]);
            abort(403, 'Access denied');
        }

        if ($clientIp === null) {
            Log::warning('AdminIpAllowlist: rejecting request with no resolvable client IP', [
                'path' => $request->path(),
            ]);
            abort(403, 'Access denied');
        }

        foreach ($allowlist as $cidr) {
            if ($this->ipInCidr($clientIp, $cidr)) {
                return $next($request);
            }
        }

        Log::info('AdminIpAllowlist: rejected IP', [
            'ip' => $clientIp,
            'path' => $request->path(),
        ]);
        abort(403, 'Access denied');
    }

    /**
     * IPv4 CIDR membership test. Malformed CIDRs return false (the request
     * falls through to the next entry, ultimately rejected if none match).
     * `0.0.0.0/0` and `/32` host CIDRs are both valid and handled by the
     * same arithmetic.
     */
    private function ipInCidr(string $ip, string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            // Treat bare addresses as host-only entries.
            return $ip === $cidr;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false) {
            Log::warning('AdminIpAllowlist: malformed CIDR entry skipped', ['cidr' => $cidr]);

            return false;
        }

        if (! ctype_digit($bits)) {
            Log::warning('AdminIpAllowlist: malformed CIDR entry skipped', ['cidr' => $cidr]);

            return false;
        }

        $bits = (int) $bits;
        if ($bits < 0 || $bits > 32) {
            Log::warning('AdminIpAllowlist: malformed CIDR entry skipped', ['cidr' => $cidr]);

            return false;
        }

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }
}
