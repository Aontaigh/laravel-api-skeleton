<?php

declare(strict_types=1);

namespace Tests\Unit\DataTransferObjects\Auth;

use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuditOutcome;
use App\Enums\AuthAuditEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for the RecordAuthAuditData DTO.
 */
#[CoversClass(RecordAuthAuditData::class)]
final class RecordAuthAuditDataTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Rewrite a serialised payload as if `outcome` had never existed.
     *
     * Drops the `outcome` key-value pair and decrements the object header's
     * declared-property count by one, which is exactly what a blob written
     * before the property was promoted would look like. Rewriting the header
     * count is what keeps `unserialize()` from rejecting the payload as
     * truncated, so the test reproduces the real legacy shape rather than a
     * corrupt one.
     *
     * @param  string $serialised the payload to rewrite
     * @return string the payload with no `outcome` entry
     */
    private static function stripOutcome(string $serialised): string
    {
        $rewritten = (string) preg_replace('/s:\d+:"outcome";E:\d+:"[^"]+";/', '', $serialised, 1);

        self::assertNotSame($serialised, $rewritten, 'The outcome entry should have been removed');

        /*
         * `unserialize()` derives the property count from the object header and
         * treats a surplus pair as trailing garbage, so the header count has to
         * drop by one alongside the entry. The braces around the backreferences
         * are required: PHP reads `$112` as capture group 112 rather than group
         * 1 followed by the digits `12`.
         */
        $count = self::declaredPropertyCount($serialised) - 1;

        return (string) preg_replace(
            '/(RecordAuthAuditData":)(\d+)(:\{)/',
            '${1}'.$count.'${3}',
            $rewritten,
            1,
        );
    }

    /**
     * Read the declared property count out of a serialised object header.
     *
     * @param  string $serialised the serialised blob to inspect
     * @return int    the declared property count
     */
    private static function declaredPropertyCount(string $serialised): int
    {
        preg_match('/^O:\d+:"[^"]+":(\d+):\{/', $serialised, $matches);

        return isset($matches[1]) ? (int) $matches[1] : 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Restore a payload serialised before `outcome` existed.
     *
     * An audit job queued on a previous deploy can carry a serialised object
     * with no `outcome` entry. PHP does not run the constructor when
     * unserialising, so the promoted-property default never applies and a bare
     * read of the missing property throws - failing that job, and every job
     * behind it, until the queue is drained. The null fallback keeps the
     * legacy row writable; `outcome` is nullable precisely for that case.
     */
    #[Test]
    public function it_restores_a_legacy_payload_without_an_outcome_to_the_default(): void
    {
        // Arrange

        $live = new RecordAuthAuditData(
            event: AuthAuditEvent::LoginFailed,
            outcome: AuditOutcome::Refused,
            userId: 42,
            email: 'legacy@example.com',
        );

        $legacy = self::stripOutcome(serialize($live));

        // Act

        /** @var RecordAuthAuditData $restored */
        $restored = unserialize($legacy);

        $copy = $restored->withLocation(null);

        // Assert

        self::assertNull($copy->outcome);
        self::assertSame(AuthAuditEvent::LoginFailed, $copy->event);
        self::assertSame(42, $copy->userId);
    }
}
