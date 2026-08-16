<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\ApiClientResource;
use App\Models\ApiClient;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for ApiClientResource serialisation.
 */
#[CoversClass(ApiClientResource::class)]
final class ApiClientResourceTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
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

        $client = new ApiClient;
        $client->id = 1;
        $client->name = 'Billing Sync';
        $client->client_id = 'billing-client';
        $client->abilities = ['users.list', 'users.list-all'];
        $client->is_active = true;
        $client->last_used_at = $lastUsedAt;
        $client->created_at = $createdAt;

        // Act

        /** @var array<string, mixed> $data */
        $data = (new ApiClientResource($client))->resolve(Request::create('/api/clients'));

        // Assert

        $this->assertSame(1, $data['id']);
        $this->assertSame('Billing Sync', $data['name']);
        $this->assertSame('billing-client', $data['client_id']);
        $this->assertSame(['users.list', 'users.list-all'], $data['abilities']);
        $this->assertTrue($data['is_active']);
        $this->assertSame(ApiDateTime::serialize($lastUsedAt), $data['last_used_at']);
        $this->assertSame(ApiDateTime::serialize($createdAt), $data['created_at']);
    }

    /**
     * Omit fields that were not selected in a sparse fieldset.
     */
    #[Test]
    public function it_omits_unselected_fields_in_a_sparse_fieldset(): void
    {
        // Arrange

        $client = new ApiClient;
        $client->id = 1;
        $client->name = 'Billing Sync';

        // Act

        /** @var array<string, mixed> $data */
        $data = (new ApiClientResource($client))->resolve(Request::create('/api/clients'));

        // Assert

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('client_id', $data);
        $this->assertArrayNotHasKey('abilities', $data);
        $this->assertArrayNotHasKey('is_active', $data);
        $this->assertArrayNotHasKey('last_used_at', $data);
        $this->assertArrayNotHasKey('created_at', $data);
    }

    /**
     * Default abilities to an empty array when the stored value is null.
     */
    #[Test]
    public function it_defaults_abilities_to_an_empty_array_when_null(): void
    {
        // Arrange

        $client = new ApiClient;
        $client->setRawAttributes([
            'id' => 1,
            'name' => 'Billing Sync',
            'client_id' => 'billing-client',
            'abilities' => null,
            'is_active' => true,
            'created_at' => '2026-01-15T10:30:00Z',
        ]);

        // Act

        /** @var array<string, mixed> $data */
        $data = (new ApiClientResource($client))->resolve(Request::create('/api/clients'));

        // Assert

        $this->assertSame([], $data['abilities']);
    }
}
