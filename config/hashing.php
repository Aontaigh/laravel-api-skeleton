<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Hash Driver
    |--------------------------------------------------------------------------
    |
    | This option controls the default hash driver Laravel uses to hash
    | passwords. This skeleton uses Argon2id in every environment; the bcrypt
    | options block below remains only because the framework's Hasher contract
    | expects it to exist.
    |
    | Supported: "bcrypt", "argon", "argon2id"
    |
    */

    'driver' => env('HASH_DRIVER', 'argon2id'),

    /*
    |--------------------------------------------------------------------------
    | Bcrypt Options
    |--------------------------------------------------------------------------
    |
    | Cost for the bcrypt driver. CI drops it to 4 so the suite stays fast;
    | production keeps 12.
    |
    */

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', true),
        'limit' => env('BCRYPT_LIMIT', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | Argon Options
    |--------------------------------------------------------------------------
    |
    | Work factors for the Argon2id driver. Raising memory or time increases
    | the cost of every new hash; the `rehash_on_login` flag below upgrades
    | existing hashes to the new factors transparently on next login.
    |
    */

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('HASH_VERIFY', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rehash On Login
    |--------------------------------------------------------------------------
    |
    | When true, a successful login rehashes the password when the stored hash
    | no longer matches the configured driver or work factors, so bumping
    | ARGON_TIME / ARGON_MEMORY upgrades every active User's hash lazily, with
    | no forced password reset. Credentials are verified in
    | AuthenticateUserAction rather than Auth::attempt(), so the flag is
    | applied there explicitly after Hash::check() succeeds.
    |
    */

    /*
     * env() returns the literal string for phpunit <env> entries, so parse it
     * strictly: "false" must map to false, anything truthy to true.
     */
    'rehash_on_login' => filter_var(env('HASH_REHASH_ON_LOGIN', true), FILTER_VALIDATE_BOOLEAN),

];
