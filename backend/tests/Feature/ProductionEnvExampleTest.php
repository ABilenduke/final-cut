<?php

/**
 * Locks the production env template against the ultrareview P0 finding where
 * FRONTEND_URL (which drives CORS allowed_origins) and SANCTUM_STATEFUL_DOMAINS
 * were missing — a deploy from the template would advertise the dev origin and
 * silently break every credentialed request from the production SPA.
 *
 * CLAUDE.md declares .env.production.example the "canonical inventory of every
 * variable the backend reads in prod", so these production-critical keys must
 * be present and non-empty.
 */
function prodEnvExampleLines(): array
{
    $path = base_path('.env.production.example');
    expect(file_exists($path))->toBeTrue();

    return array_values(array_filter(
        array_map('trim', file($path, FILE_IGNORE_NEW_LINES)),
        fn (string $line): bool => $line !== '' && ! str_starts_with($line, '#'),
    ));
}

function prodEnvValue(string $key): ?string
{
    foreach (prodEnvExampleLines() as $line) {
        if (str_starts_with($line, $key.'=')) {
            // Strip an inline "# comment" and surrounding whitespace.
            $value = trim(preg_replace('/\s+#.*$/', '', substr($line, strlen($key) + 1)));

            return $value;
        }
    }

    return null;
}

test('production env example declares a non-empty FRONTEND_URL on https', function (): void {
    $value = prodEnvValue('FRONTEND_URL');

    expect($value)->not->toBeNull()
        ->and($value)->toStartWith('https://')
        ->and($value)->not->toContain('finalcut.test'); // must not be the dev origin
});

test('production env example declares a non-empty SANCTUM_STATEFUL_DOMAINS', function (): void {
    $value = prodEnvValue('SANCTUM_STATEFUL_DOMAINS');

    expect($value)->not->toBeNull()
        ->and($value)->not->toBe('');
});

test('cors config resolves allowed_origins from the FRONTEND_URL env var', function (): void {
    // Locks the contract that the CORS origin is env-driven (not hardcoded), so
    // the prod template value above is the thing that actually takes effect.
    $source = file_get_contents(config_path('cors.php'));

    expect($source)->toContain("env('FRONTEND_URL'");
});
