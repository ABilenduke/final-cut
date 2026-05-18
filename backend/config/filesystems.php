<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        // The public disk backs every Filament upload (calendar events,
        // featured slides, menu items). In dev it stays local; in prod the
        // `s3` driver is pointed at DigitalOcean Spaces. Spaces speaks the S3
        // API but it is not AWS — credentials live under DO_SPACES_* so the
        // distinction is obvious in env files and runbooks.
        'public' => env('PUBLIC_DISK_DRIVER', 'local') === 's3'
            ? [
                'driver' => 's3',
                'key' => env('DO_SPACES_KEY'),
                'secret' => env('DO_SPACES_SECRET'),
                'region' => env('DO_SPACES_REGION'),
                'bucket' => env('DO_SPACES_BUCKET'),
                'endpoint' => env('DO_SPACES_ENDPOINT'),
                // Spaces uses virtual-hosted-style addressing by default;
                // pinned to false because path-style is the wrong choice here
                // and we don't want a stray env flip to break uploads.
                'use_path_style_endpoint' => false,
                // DO_SPACES_URL is the CDN host only (e.g.
                // https://andrewbilendukecdn.nyc3.cdn.digitaloceanspaces.com)
                // — NOT including the bucket prefix. Laravel's S3 url() builder
                // prepends the disk `root` (the bucket prefix) to the path
                // before concatenating, so we must not double-include it.
                'url' => rtrim((string) env('DO_SPACES_URL'), '/'),
                // All Filament uploads target this disk; the `root` value is
                // the in-bucket folder uploads land under. Storage::url() also
                // prepends this prefix, producing the CDN-resolvable URL.
                'root' => trim((string) env('DO_SPACES_BUCKET_PREFIX', 'finalcut'), '/'),
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ]
            : [
                'driver' => 'local',
                'root' => storage_path('app/public'),
                'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
