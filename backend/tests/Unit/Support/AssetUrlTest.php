<?php

declare(strict_types=1);

use App\Support\AssetUrl;
use Illuminate\Support\Facades\Config;

function configureAssetUrlLocalPublicDisk(): void
{
    Config::set('filesystems.disks.public', [
        'driver' => 'local',
        'root' => storage_path('app/public'),
        'url' => 'http://localhost/storage',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
    ]);

    app('filesystem')->forgetDisk('public');
}

it('returns null for null', function (): void {
    expect(AssetUrl::resolve(null))->toBeNull();
});

it('returns null for empty string', function (): void {
    expect(AssetUrl::resolve(''))->toBeNull();
});

it('passes absolute https URLs through unchanged', function (): void {
    $url = 'https://cdn.example.test/finalcut/concessions/bottle_of_water.webp';

    expect(AssetUrl::resolve($url))->toBe($url);
});

it('passes absolute http URLs through unchanged', function (): void {
    $url = 'http://localhost:9000/storage/foo.png';

    expect(AssetUrl::resolve($url))->toBe($url);
});

it('resolves relative paths against the local public disk', function (): void {
    configureAssetUrlLocalPublicDisk();

    $resolved = AssetUrl::resolve('menu-items/something.webp');

    expect($resolved)->toBe('http://localhost/storage/menu-items/something.webp');
});

it('resolves relative paths against the s3-backed public disk when configured', function (): void {
    Config::set('filesystems.disks.public', [
        'driver' => 's3',
        'key' => 'test-key',
        'secret' => 'test-secret',
        'region' => 'nyc3',
        'bucket' => 'assets',
        'endpoint' => 'https://nyc3.digitaloceanspaces.com',
        'use_path_style_endpoint' => false,
        // Production-shape config: DO_SPACES_URL holds only the CDN host;
        // the bucket prefix lives in `root` and is appended by the S3 url
        // builder. This mirrors what config/filesystems.php produces when
        // PUBLIC_DISK_DRIVER=s3.
        'url' => 'https://cdn.example.test',
        'root' => 'finalcut',
        'visibility' => 'public',
        'throw' => false,
        'report' => false,
    ]);

    // Forget the cached disk instance so the new config takes effect.
    app('filesystem')->forgetDisk('public');

    expect(AssetUrl::resolve('concessions/bottle_of_water.webp'))
        ->toBe('https://cdn.example.test/finalcut/concessions/bottle_of_water.webp');
});
