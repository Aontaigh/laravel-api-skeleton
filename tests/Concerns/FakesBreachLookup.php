<?php

declare(strict_types=1);

namespace Tests\Concerns;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Fakes the HaveIBeenPwned range lookup behind `uncompromised()`.
 *
 * Breach assertions must never touch the live HIBP endpoint: an unreachable
 * API fails open, so a network-dependent test would wrongly pass a breached
 * password instead of failing loudly.
 */
trait FakesBreachLookup
{
    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Fake the HIBP range lookup, reporting only the given passwords as breached.
     *
     * @param  list<string> $breachedPasswords passwords the fake reports as breached
     * @return void
     */
    protected function fakeBreachLookup(array $breachedPasswords = []): void
    {
        $hashes = array_map(
            static fn (string $password): string => strtoupper(sha1($password)),
            $breachedPasswords,
        );

        Http::fake(static function (Request $httpRequest) use ($hashes): PromiseInterface {
            $prefix = basename($httpRequest->url());

            $lines = [];

            foreach ($hashes as $hash) {
                if (str_starts_with($hash, $prefix)) {
                    $lines[] = substr($hash, 5).':100';
                }
            }

            return Http::response(implode("\n", $lines));
        });
    }
}
