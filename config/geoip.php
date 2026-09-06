<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | MaxMind Credentials
    |--------------------------------------------------------------------------
    |
    | Used only by `geoip:update` to download GeoLite2-City via HTTP Basic Auth.
    | Never read these from a web request. Leave empty to skip the download;
    | lookups fail open. `staging` and `production` refresh weekly via
    | `php artisan schedule:run` when both values are set.
    |
    */

    'account_id' => env('MAXMIND_ACCOUNT_ID', ''),

    'license_key' => env('MAXMIND_LICENSE_KEY', ''),

    /*
    |--------------------------------------------------------------------------
    | GeoLite2 City Database Path
    |--------------------------------------------------------------------------
    |
    | Absolute path, or a path relative to the project root. A relative value
    | is always anchored to `base_path()`, so the file is found regardless of
    | the process working directory. Missing files fail open (null city and
    | country) rather than taking session registration down.
    |
    */

    'database' => env('GEOIP_DATABASE', storage_path('geoip/GeoLite2-City.mmdb')),

    /*
    |--------------------------------------------------------------------------
    | Local Fallback IP
    |--------------------------------------------------------------------------
    |
    | GeoLite2 has no private or loopback ranges. Sail logins arrive as
    | 172.x / 127.0.0.1, so `local` looks this public address up instead of
    | skipping. Ignored outside `local`. Must be a public unicast IP.
    |
    */

    'local_fallback_ip' => env('GEOIP_LOCAL_FALLBACK_IP', '8.8.8.8'),

    /*
    |--------------------------------------------------------------------------
    | Direct Download
    |--------------------------------------------------------------------------
    |
    | GeoLite2 permalink MaxMind documents for Basic Auth downloads. Overridable
    | in tests so `Http::fake` can intercept without hitting MaxMind.
    |
    */

    'download_url' => env(
        'GEOIP_DOWNLOAD_URL',
        'https://download.maxmind.com/geoip/databases/GeoLite2-City/download',
    ),

];
