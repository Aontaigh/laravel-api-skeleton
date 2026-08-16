<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PersonalAccessTokenResource;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for PersonalAccessTokenResource serialisation.
 */
#[CoversClass(PersonalAccessTokenResource::class)]
final class PersonalAccessTokenResourceTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------​
    | Tests
    |--------------------------------------------------------------------------​
    */

    /**
     * Serialise all fields when no sparse fieldset is requested.
     */
    #[Test]
    public function it_includes_all_fields_when_no_sparse_fieldset_is_requested(): void
    {
        // Arrange

        $createdAt = Carbon::parse('2026-01-15T10:30:00Z');
        $lastUsedAt = Carbon::parse('2026-02-01T12:00:00Z');

        $token = new PersonalAccessToken;
        $token->id = 1;
        $token->name = 'cli-token';
        $token->abilities = ['users.list', 'users.show'];
        $token->last_used_at = $lastUsedAt;
        $token->expires_at = null;
        $token->created_at = $createdAt;

        // Act

        $data = (new PersonalAccessTokenResource($token))->resolve(Request::create('/api/tokens'));

        // Assert

        $this->assertSame(1, $data['id']);
        $this->assertSame('cli-token', $data['name']);
        $this->assertSame(['users.list', 'users.show'], $data['abilities']);
        $this->assertSame(ApiDateTime::serialize($lastUsedAt), $data['last_used_at']);
        $this->assertNull($data['expires_at']);
        $this->assertSame(ApiDateTime::serialize($createdAt), $data['created_at']);
    }

    /**
     * Omit fields that were not selected in a sparse fieldset.
     */
    #[Test]
    public function it_omits_unselected_fields_in_a_sparse_fieldset(): void
    {
        // Arrange

        $token = new PersonalAccessToken;
        $token->id = 1;
        $token->name = 'cli-token';

        // Act

        $data = (new PersonalAccessTokenResource($token))->resolve(Request::create('/api/tokens'));

        // Assert

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('abilities', $data);
        $this->assertArrayNotHasKey('last_used_at', $data);
        $this->assertArrayNotHasKey('expires_at', $data);
        $this->assertArrayNotHasKey('created_at', $data);
    }

    /**
     * Default abilities to an empty array when the attribute is null.
     */
    #[Test]
    public function it_defaults_abilities_to_an_empty_array_when_null(): void
    {
        // Arrange

        $token = new PersonalAccessToken;
        $token->id = 1;
        $token->name = 'cli-token';
        $token->abilities = null;

        // Act

        $data = (new PersonalAccessTokenResource($token))->resolve(Request::create('/api/tokens'));

        // Assert

        $this->assertSame([], $data['abilities']);
    }
}
