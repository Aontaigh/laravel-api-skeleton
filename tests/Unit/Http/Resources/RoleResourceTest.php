<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\RoleResource;
use App\Support\ApiDateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Unit tests for RoleResource serialisation.
 */
#[CoversClass(RoleResource::class)]
final class RoleResourceTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------​
    | Tests
    |--------------------------------------------------------------------------​
    */

    /**
     * Serialise all scalar fields when no sparse fieldset is requested.
     */
    #[Test]
    public function it_includes_all_scalar_fields_when_no_sparse_fieldset_is_requested(): void
    {
        // Arrange

        $createdAt = Carbon::parse('2026-01-15T10:30:00Z');

        $role = new Role;
        $role->id = 1;
        $role->name = 'Admin';
        $role->created_at = $createdAt;

        // Act

        $data = (new RoleResource($role))->resolve(Request::create('/api/roles'));

        // Assert

        $this->assertSame(1, $data['id']);
        $this->assertSame('Admin', $data['name']);
        $this->assertSame(ApiDateTime::serialize($createdAt), $data['created_at']);
        $this->assertArrayNotHasKey('permissions', $data);
    }

    /**
     * Omit fields that were not selected in a sparse fieldset.
     */
    #[Test]
    public function it_omits_unselected_fields_in_a_sparse_fieldset(): void
    {
        // Arrange

        $role = new Role;
        $role->id = 1;

        // Act

        $data = (new RoleResource($role))->resolve(Request::create('/api/roles'));

        // Assert

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayNotHasKey('name', $data);
        $this->assertArrayNotHasKey('created_at', $data);
    }

    /**
     * Include permissions only when the relation is loaded.
     */
    #[Test]
    public function it_includes_permissions_only_when_the_relation_is_loaded(): void
    {
        // Arrange

        $role = new Role;
        $role->id = 1;
        $role->name = 'Admin';
        $role->setRelation('permissions', collect([
            tap(new Permission, static function (Permission $p): void {
                $p->id = 1;
                $p->name = 'users.list';
            }),
        ]));

        // Act

        $data = (new RoleResource($role))->resolve(Request::create('/api/roles'));

        // Assert

        $this->assertArrayHasKey('permissions', $data);
        $this->assertCount(1, $data['permissions']);
        $this->assertSame(1, $data['permissions'][0]['id']);
        $this->assertSame('users.list', $data['permissions'][0]['name']);
    }
}
