<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\UserResource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Unit tests for UserResource serialisation.
 */
#[CoversClass(UserResource::class)]
final class UserResourceTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------​
    | Setup
    |--------------------------------------------------------------------------​
    */

    /**
     * Build a User with all attributes set directly.
     */
    private function fullUser(): User
    {
        $user = new User;
        $user->id = 1;
        $user->name = 'Alice';
        $user->email = 'alice@example.com';
        $user->created_at = Carbon::parse('2026-01-15T10:30:00Z');

        return $user;
    }

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

        $user = $this->fullUser();

        // Act

        $data = (new UserResource($user))->resolve(Request::create('/api/users'));

        // Assert

        /*
         * Email is omitted because there is no authenticated viewer.
         */

        $this->assertSame(1, $data['id']);
        $this->assertSame('Alice', $data['name']);
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayNotHasKey('team', $data);
        $this->assertArrayNotHasKey('role', $data);
    }

    /**
     * Omit fields that were not selected in a sparse fieldset.
     */
    #[Test]
    public function it_omits_unselected_fields_in_a_sparse_fieldset(): void
    {
        // Arrange

        $user = new User;
        $user->id = 1;
        $user->name = 'Alice';

        // Act

        $data = (new UserResource($user))->resolve(Request::create('/api/users'));

        // Assert

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('name', $data);
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('created_at', $data);
    }

    /**
     * Omit email when there is no authenticated viewer.
     */
    #[Test]
    public function it_omits_email_when_there_is_no_authenticated_viewer(): void
    {
        // Arrange

        $user = $this->fullUser();

        $request = Request::create('/api/users');
        $request->setUserResolver(static fn () => null);

        // Act

        $data = (new UserResource($user))->resolve($request);

        // Assert

        $this->assertArrayNotHasKey('email', $data);
    }

    /**
     * Include team only when the relation is loaded and non-default.
     */
    #[Test]
    public function it_includes_team_only_when_the_relation_is_loaded(): void
    {
        // Arrange

        $user = $this->fullUser();

        $team = new Team;
        $team->id = 5;
        $team->name = 'Engineering';

        $user->setRelation('team', $team);

        // Act

        $data = (new UserResource($user))->resolve(Request::create('/api/users'));

        // Assert

        $this->assertArrayHasKey('team', $data);
        $this->assertSame(5, $data['team']['id']);
        $this->assertSame('Engineering', $data['team']['name']);
    }

    /**
     * Omit team when the relation is not loaded.
     */
    #[Test]
    public function it_omits_team_when_the_relation_is_not_loaded(): void
    {
        // Arrange

        $user = $this->fullUser();

        // Act

        $data = (new UserResource($user))->resolve(Request::create('/api/users'));

        // Assert

        $this->assertArrayNotHasKey('team', $data);
    }

    /**
     * Include role only when the roles relation is loaded and non-empty.
     */
    #[Test]
    public function it_includes_role_only_when_the_roles_relation_is_loaded_and_non_empty(): void
    {
        // Arrange

        $user = $this->fullUser();

        $role = new Role;
        $role->id = 1;
        $role->name = 'Admin';

        $user->setRelation('roles', collect([$role]));

        // Act

        $data = (new UserResource($user))->resolve(Request::create('/api/users'));

        // Assert

        $this->assertArrayHasKey('role', $data);
        $this->assertSame(1, $data['role']['id']);
        $this->assertSame('Admin', $data['role']['name']);
    }

    /**
     * Omit role when the roles relation is loaded but empty.
     */
    #[Test]
    public function it_omits_role_when_the_roles_relation_is_loaded_but_empty(): void
    {
        // Arrange

        $user = $this->fullUser();
        $user->setRelation('roles', collect());

        // Act

        $data = (new UserResource($user))->resolve(Request::create('/api/users'));

        // Assert

        $this->assertArrayNotHasKey('role', $data);
    }
}
