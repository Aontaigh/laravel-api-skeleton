<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Tokens;

use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Exceptions\InvalidTokenAbilitiesException;
use App\Models\User;
use App\Services\Permissions\PermissionAbilityCatalog;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\UnitTestCase;

/**
 * Unit tests for CreatePersonalAccessTokenAction.
 *
 * Uses an in-memory catalog resolver — no database, no HTTP. The User is
 * unsaved; `createToken()` is never reached when the catalog rejects abilities.
 */
#[CoversClass(CreatePersonalAccessTokenAction::class)]
final class CreatePersonalAccessTokenActionTest extends UnitTestCase
{
    /*
    |--------------------------------------------------------------------------
    | Tests
    |--------------------------------------------------------------------------
    */

    /**
     * Not issue a Token when the catalog rejects abilities.
     */
    #[Test]
    public function it_does_not_issue_a_token_when_the_catalog_rejects_abilities(): void
    {
        // Arrange

        $catalog = new PermissionAbilityCatalog(fn (): array => ['tokens.list-own']);

        $data = new CreateTokenData(
            forUser: new User,
            name: 'Bad Token',
            abilities: ['read'],
        );

        // Act + Assert

        $this->expectException(InvalidTokenAbilitiesException::class);

        (new CreatePersonalAccessTokenAction($catalog))->execute($data);
    }
}
