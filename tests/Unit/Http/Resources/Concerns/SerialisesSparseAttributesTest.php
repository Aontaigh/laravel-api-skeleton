<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Concerns;

use App\Http\Resources\Concerns\SerialisesSparseAttributes;
use App\Models\User;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Http\Resources\Concerns\SparseAttributesClosureHarnessResource;
use Tests\Support\Http\Resources\Concerns\SparseAttributesHarnessResource;
use Tests\TestCase;

/**
 * Unit tests for sparse attribute serialisation on JsonResources.
 */
#[CoversTrait(SerialisesSparseAttributes::class)]
final class SerialisesSparseAttributesTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Include the attribute when the column exists on the model row.
     */
    #[Test]
    public function it_includes_the_attribute_when_the_column_was_selected(): void
    {
        // Arrange

        $user = new User;
        $user->id = 1;
        $user->name = 'Alice';

        // Act

        $data = (new SparseAttributesHarnessResource($user))->resolve(Request::create('/api/users'));

        // Assert

        $this->assertSame('Alice', $data['name']);
    }

    /**
     * Omit the attribute when the column was not selected on the model row.
     */
    #[Test]
    public function it_omits_the_attribute_when_the_column_was_not_selected(): void
    {
        // Arrange

        $user = new User;
        $user->id = 1;

        // Act

        $data = (new SparseAttributesHarnessResource($user))->resolve(Request::create('/api/users'));

        // Assert

        $this->assertArrayNotHasKey('name', $data);
    }

    /**
     * Skip the value Closure when the column was not selected.
     */
    #[Test]
    public function it_does_not_evaluate_the_value_closure_when_the_column_was_not_selected(): void
    {
        // Arrange

        $user = new User;
        $user->id = 1;

        // Act

        $data = (new SparseAttributesClosureHarnessResource($user))->resolve(Request::create('/api/users'));

        // Assert

        $this->assertArrayNotHasKey('email', $data);
    }
}
