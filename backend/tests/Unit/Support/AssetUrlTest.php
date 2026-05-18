<?php

declare(strict_types=1);

use App\Support\AssetUrl;
use Illuminate\Support\Facades\Config;

it('returns null for null', function (): void {
    expect(AssetUrl::resolve(null))->toBeNull();
});

it('returns null for empty string', function (): void {
    expect(AssetUrl::resolve(''))->toBeNull();
});

it('passes absolute https URLs through unchanged', function (): void {
    $url = 'https://andrewbilendukecdn.nyc3.cdn.digitaloceanspaces.com/finalcut/concessions/bottle_of_water.webp';

    expect(AssetUrl::resolve($url))->toBe($url);
});

it('passes absolute http URLs through unchanged', function (): void {
    $url = 'http://localhost:9000/storage/foo.png';

    expect(AssetUrl::resolve($url))->toBe($url);
});

it('resolves relative paths against the local public disk', function (): void {
    // Tests inherit the project's local-driver public disk config (phpunit.xml
    // doesn't set PUBLIC_DISK_DRIVER), so url() returns the APP_URL/storage form.
    $resolved = AssetUrl::resolve('menu-items/something.webp');

    expect($resolved)->toEndWith('/storage/menu-items/something.webp')
        ->and($resolved)->toStartWith('http');
});

it('resolves relative paths against the s3-backed public disk when configured', function (): void {
    Config::set('filesystems.disks.public', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'nyc3',
        'bucket' => 'andrewbilendukecdn',
        'endpoint' => 'https://nyc3.digitaloceanspaces.com',
        'use_path_style_endpoint' => false,
        // Production-shape config: DO_SPACES_URL holds only the CDN host;
        // the bucket prefix lives in `root` and is appended by the S3 url
        // builder. This mirrors what config/filesystems.php produces when
        // PUBLIC_DISK_DRIVER=s3.
        'url' => 'https://andrewbilendukecdn.nyc3.cdn.digitaloceanspaces.com',
        'root' => 'finalcut',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
    ]);

    // Forget the cached disk instance so the new config takes effect.
    app('filesystem')->forgetDisk('public');

    expect(AssetUrl::resolve('concessions/bottle_of_water.webp'))
        ->toBe('https://andrewbilendukecdn.nyc3.cdn.digitaloceanspaces.com/finalcut/concessions/bottle_of_water.webp');
});
