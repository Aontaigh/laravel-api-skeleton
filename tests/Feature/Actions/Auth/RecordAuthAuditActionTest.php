<?php

declare(strict_types=1);

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\RecordAuthAuditAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\Enums\AuthAuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature tests for RecordAuthAuditAction against the database.
 */
#[CoversClass(RecordAuthAuditAction::class)]
final class RecordAuthAuditActionTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use RefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Cap an oversized User-Agent before the audit row is written.
     */
    #[Test]
    public function it_caps_an_oversized_user_agent_before_persisting_the_audit_row(): void
    {
        // Arrange

        /** @var User $user */
        $user = User::factory()->create();

        $oversizedUserAgent = str_repeat('B', 1500);

        // Act

        app(RecordAuthAuditAction::class)->execute(new RecordAuthAuditData(
            event: AuthAuditEvent::ForcedLogout,
            userId: $user->id,
            email: $user->email,
            ipAddress: '127.0.0.1',
            userAgent: $oversizedUserAgent,
        ));

        // Assert

        $this->assertDatabaseHas('auth_audit_logs', [
            'user_id' => $user->id,
            'event' => AuthAuditEvent::ForcedLogout->value,
            'user_agent' => str_repeat('B', 1024),
        ]);
    }
}
