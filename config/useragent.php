<?php

declare(strict_types=1);

use App\Services\UserAgent\BasicUserAgentParser;
use App\Services\UserAgent\NullUserAgentParser;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Parser Driver
    |--------------------------------------------------------------------------
    |
    | The user-agent parser bound to the UserAgentParser contract. Swapping the
    | implementation is a one-line change: add a driver below and flip this key.
    | The `null` driver disables parsing entirely.
    |
    */

    'driver' => env('USER_AGENT_PARSER', 'basic'),

    /*
    |--------------------------------------------------------------------------
    | Available Drivers
    |--------------------------------------------------------------------------
    |
    | Map of driver name to the concrete UserAgentParser implementation. The
    | interface and DTO never change when a driver is added.
    |
    */

    'drivers' => [
        'basic' => BasicUserAgentParser::class,
        'null' => NullUserAgentParser::class,
    ],

];
