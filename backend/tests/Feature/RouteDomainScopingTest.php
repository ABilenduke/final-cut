<?php

use Illuminate\Support\Facades\Route;

/**
 * Locks in the route-domain constraints that keep the customer and admin
 * surfaces disjoint: API + web fallback routes live on the primary domain
 * only, Filament panel routes live on the admin domain only, and no route
 * is allowed to answer on every host.
 */
beforeEach(function (): void {
    $this->primaryDomain = config('app.primary_domain');
    $this->primaryDomains = config('app.primary_domains');
    $this->adminDomain = config('filament.admin_domain');
});

test('customer api routes are scoped to the primary domain', function (): void {
    $apiRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/'))
        // Sanctum's CSRF route is registered by its service provider (not by
        // routes/api.php), so it falls outside our domain wrap.
        ->reject(fn ($route) => $route->uri() === 'api/sanctum/csrf-cookie');

    expect($apiRoutes)->not->toBeEmpty();

    foreach ($apiRoutes as $route) {
        expect($route->domain())
            ->toBeIn($this->primaryDomains, sprintf(
                'Route [%s %s] is scoped to [%s] which is not one of the configured primary domains [%s].',
                implode('|', $route->methods()),
                $route->uri(),
                $route->domain() ?? 'null',
                implode(', ', $this->primaryDomains),
            ));
    }
});

test('web.php fallback routes are scoped to the primary domain', function (): void {
    // URI `/` collides with Filament's dashboard (also at `/` on the admin
    // domain), so restrict the search to customer-domain routes before
    // matching URIs.
    $customerRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => in_array($route->domain(), $this->primaryDomains, true));

    foreach (['/', '{fallbackPlaceholder}'] as $uri) {
        $match = $customerRoutes->first(fn ($route) => $route->uri() === $uri);

        expect($match)->not->toBeNull(sprintf(
            'Expected web fallback route [%s] on one of the primary domains [%s].',
            $uri,
            implode(', ', $this->primaryDomains),
        ));
    }
});

test('filament panel routes are scoped to the admin domain', function (): void {
    $filamentRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'filament.admin'));

    expect($filamentRoutes)->not->toBeEmpty();

    foreach ($filamentRoutes as $route) {
        expect($route->domain())
            ->toBe($this->adminDomain, sprintf(
                'Route [%s] is missing the admin-domain scope.',
                $route->getName(),
            ));
    }
});

test('no registered route may answer on every host except vendor infrastructure', function (): void {
    // Framework and vendor service-provider routes that must answer on any
    // host (health check, CSRF seed, Livewire update/asset endpoints,
    // Filament export/import download endpoints). Everything the
    // application itself registers — customer api/web and admin panel
    // routes — must be domain-scoped.
    $exemptUris = [
        'up',
        'api/sanctum/csrf-cookie',
    ];

    $exemptNamePrefixes = [
        'sanctum.',
        'livewire.',
        'default-livewire.',
        'filament.exports.',
        'filament.imports.',
    ];

    $exemptUriPrefixes = [
        'livewire-',
        'livewire/',
        'filament/exports/',
        'filament/imports/',
    ];

    $unscoped = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => $route->domain() === null)
        ->reject(fn ($route) => in_array($route->uri(), $exemptUris, true))
        ->reject(function ($route) use ($exemptNamePrefixes) {
            $name = (string) $route->getName();

            foreach ($exemptNamePrefixes as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    return true;
                }
            }

            return false;
        })
        ->reject(function ($route) use ($exemptUriPrefixes) {
            foreach ($exemptUriPrefixes as $prefix) {
                if (str_starts_with($route->uri(), $prefix)) {
                    return true;
                }
            }

            return false;
        })
        ->map(fn ($route) => sprintf('%s %s', implode('|', $route->methods()), $route->uri()))
        ->values();

    expect($unscoped->all())->toBe(
        [],
        'Every application route must be scoped to either the primary or admin domain. Unscoped routes: '
        .$unscoped->implode(', '),
    );
});
