<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\AuthenticatedUserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for AuthenticatedUserResource serialisation.
 */
#[CoversClass(AuthenticatedUserResource::class)]
final class AuthenticatedUserResourceTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Setup
    |--------------------------------------------------------------------------
    */

    /**
     * Build a User with scalar attributes set directly.
     */
    private function authenticatedUser(): User
    {
        $user = new User;
        $user->id = 3;
        $user->name = 'Alice';
        $user->email = 'alice@example.com';
        $user->created_at = Carbon::parse('2026-01-15T10:30:00Z');

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Always include email for the session owner.
     */
    #[Test]
    public function it_always_includes_email_for_the_session_owner(): void
    {
        // Arrange

        $user = $this->authenticatedUser();

        // Act

        /** @var array<string, mixed> $data */
        $data = (new AuthenticatedUserResource($user))->resolve(Request::create('/api/auth/login'));

        // Assert

        $this->assertSame(3, $data['id']);
        $this->assertSame('Alice', $data['name']);
        $this->assertSame('alice@example.com', $data['email']);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayNotHasKey('team', $data);
        $this->assertArrayNotHasKey('role', $data);
    }
}
