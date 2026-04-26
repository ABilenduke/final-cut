<?php

return [
    /*
    |---------------------------------------------------------------------------
    | Admin IP Allowlist
    |---------------------------------------------------------------------------
    |
    | Comma-separated list of IPv4 addresses or CIDR blocks allowed to reach
    | the admin panel. Enforced at two layers:
    |
    |   1. nginx — `allow … ; deny all;` block in admin.conf.template
    |   2. Laravel — App\Http\Middleware\AdminIpAllowlist
    |
    | An empty list in a non-local environment fails closed at both layers
    | (every request returns 403). The emergency_open flag below is the
    | only way to unblock requests when the allowlist is empty; it logs
    | error-level on every request so it cannot be left on quietly.
    |
    | v1 is IPv4-only. IPv6 ingress is rejected at the middleware layer with
    | a logged warning; adding IPv6 support requires updating the middleware,
    | the nginx allow/deny block, and adding a parallel ip6tables-multiport
    | Fail2ban jail in the same change.
    */

    'ip_allowlist' => env('ADMIN_IP_ALLOWLIST', ''),

    /*
    |---------------------------------------------------------------------------
    | Allowlist Emergency Open
    |---------------------------------------------------------------------------
    |
    | Time-boxed escape hatch for the deploy chicken-and-egg: because the
    | allowlist fails closed when empty, a fresh production deploy with no
    | CIDRs configured would reject every request — including the ops
    | engineer trying to set the CIDRs. Setting this flag to true bypasses
    | the layer-2 check when the allowlist is empty.
    |
    | Usage:
    |   - false (default) → fail closed if allowlist empty. Production
    |     resting state.
    |   - true → allow all requests when allowlist is empty. Logs error-level
    |     on every request so the bypass cannot be silent.
    |
    | NEVER leave this true outside a bootstrap window. Unset it the moment
    | real CIDRs are live.
    */

    'ip_allowlist_emergency_open' => env('ADMIN_IP_ALLOWLIST_EMERGENCY_OPEN', false),
];
