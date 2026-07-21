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
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         * Covers, hero images and every other uploaded asset the public site renders.
         *
         * THE URL IS ROOT-RELATIVE, AND THAT IS THE FIX FOR A REAL BUG.
         *
         * It used to be `APP_URL.'/storage'` — Laravel's stock line. That bakes ONE absolute
         * host into every image URL in the app, and it is wrong the moment the host you
         * browse is not the host in .env. On WAMP that is the normal case: APP_URL still said
         * http://127.0.0.1:8000 (the `artisan serve` port) while the site was being served by
         * Apache on :80, so every <img> pointed at a port with nothing listening and EVERY
         * image on the site rendered as the neutral placeholder.
         *
         * It is easy to miss because the stylesheet still loads: Vite's asset() derives its
         * URLs from the CURRENT REQUEST ROOT, so the page looks perfect and only the images —
         * the one thing that goes through Storage::url() — are dead.
         *
         * '/storage' resolves against whatever origin served the page, so artisan serve, the
         * Apache vhost and cPanel all work with no .env change and nothing to keep in sync.
         *
         * PUBLIC_DISK_URL is the escape hatch, and there are two real reasons to set it:
         *   - the app is served from a SUBDIRECTORY (http://localhost/lcc-journal-system/public)
         *     rather than the root of its own host — then set /lcc-journal-system/public/storage
         *   - assets move to a CDN or a bucket, which needs a full absolute origin
         *
         * Safe because nothing machine-readable consumes these URLs: the citation meta tags,
         * the sitemap, the Atom feed and the Crossref deposit all build their URLs from
         * route()/landingUrl(), and there is no og:image anywhere. Only the React UI reads
         * Media::url, where a root-relative src is exactly right.
         */
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('PUBLIC_DISK_URL', '/storage'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Manuscript PDFs and submission files.
         *
         * PRIVATE on purpose. These are streamed through ArticleController::pdf() rather
         * than exposed via a public symlink, because:
         *   - downloads must be counted (article_metric_daily), and a symlink cannot be;
         *   - unpublished manuscripts under peer review must not be guessable by URL —
         *     an embargoed paper leaking early is a research-integrity incident.
         * 'throw' => true: a missing file must fail loudly, not return an empty 200 that
         * Scholar would record as a broken citation_pdf_url.
         */
        'private' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'visibility' => 'private',
            'throw' => true,
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
