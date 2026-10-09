<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Console\Scheduling\Event as ScheduledEvent;
use Illuminate\Console\Scheduling\Schedule;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The framework's expired-token pruner must be scheduled, and must sweep every
 * row already expired rather than only those past its 24-hour default.
 */
#[CoversNothing]
final class ScheduledTokenPruningTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * An expired Personal Access Token leaves the table on the next run.
     *
     * @return void
     */
    #[Test]
    public function it_sweeps_every_expired_token_at_the_next_run(): void
    {
        // Act

        $this->artisan('schedule:list')->assertSuccessful();

        // Assert

        $commands = array_map(
            static fn (ScheduledEvent $event): string => (string) $event->command,
            $this->app->make(Schedule::class)->events(),
        );

        $this->assertStringContainsString(
            'sanctum:prune-expired --hours=0',
            implode("\n", $commands),
            'The pruner must sweep every row already expired, not only those older than the default 24 hours.',
        );
    }
}
