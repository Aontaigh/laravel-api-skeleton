<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\UserReferenceResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for the compact User reference payload.
 */
#[CoversClass(UserReferenceResource::class)]
final class UserReferenceResourceTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Setup / Teardown
    |--------------------------------------------------------------------------
    */

    /**
     * Build a User carrying every column the reference can emit.
     *
     * @return User the constructed User
     */
    private function fullUser(): User
    {
        $user = new User;
        $user->id = 4;
        $user->name = 'Ada Lovelace';
        $user->email = 'ada@example.com';
        $user->phone = '+441234567890';
        $user->created_at = Carbon::parse('2026-01-15T10:30:00Z');

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Emit every reference key when no sparse fieldset is requested.
     */
    #[Test]
    public function it_includes_every_reference_key_when_no_sparse_fieldset_is_requested(): void
    {
        // Arrange

        $user = $this->fullUser();

        // Act

        $payload = (new UserReferenceResource($user))->resolve(Request::create('/'));

        // Assert

        self::assertSame([
            'id' => 4,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '+441234567890',
            'created_at' => '2026-01-15T10:30:00+00:00',
        ], $payload);
    }

    /**
     * Omit a key the caller did not select rather than half-populating it.
     */
    #[Test]
    public function it_omits_keys_a_sparse_fieldset_left_out(): void
    {
        // Arrange

        $user = $this->fullUser();
        $user->syncOriginal();
        $user->setRawAttributes(['id' => 4, 'name' => 'Ada Lovelace']);

        // Act

        $payload = (new UserReferenceResource($user))->resolve(Request::create('/'));

        // Assert

        self::assertSame(['id' => 4, 'name' => 'Ada Lovelace'], $payload);
    }

    /**
     * Never emit the profile keys that made the old payload heavy.
     *
     * Roles, permissions, and MFA state belong to `UserResource`; a list of rows
     * must not repeat them per party.
     */
    #[Test]
    public function it_never_emits_profile_keys(): void
    {
        // Arrange

        $user = $this->fullUser();

        // Act

        $payload = (new UserReferenceResource($user))->resolve(Request::create('/'));

        // Assert

        self::assertArrayNotHasKey('roles', $payload);
        self::assertArrayNotHasKey('permissions', $payload);
        self::assertArrayNotHasKey('two_factor', $payload);
    }
}
