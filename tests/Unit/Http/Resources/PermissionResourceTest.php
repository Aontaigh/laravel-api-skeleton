<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\PermissionResource;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\UnitTestCase;

/**
 * Unit tests for PermissionResource serialisation.
 */
#[CoversClass(PermissionResource::class)]
final class PermissionResourceTest extends UnitTestCase
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

        $permission = new Permission;
        $permission->id = 1;
        $permission->name = 'users.list';

        // Act

        $data = (new PermissionResource($permission))->resolve(Request::create('/api/roles'));

        // Assert

        $this->assertSame(['id' => 1, 'name' => 'users.list'], $data);
    }

    /**
     * Omit fields that were not selected in a sparse fieldset.
     */
    #[Test]
    public function it_omits_unselected_fields_in_a_sparse_fieldset(): void
    {
        // Arrange

        $permission = new Permission;
        $permission->name = 'users.list';

        // Act

        $data = (new PermissionResource($permission))->resolve(Request::create('/api/roles'));

        // Assert

        $this->assertArrayNotHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertSame('users.list', $data['name']);
    }
}
