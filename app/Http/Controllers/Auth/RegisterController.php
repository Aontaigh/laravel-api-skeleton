<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Actions\Tokens\CreatePersonalAccessTokenAction;
use App\DataTransferObjects\Auth\RecordAuthAuditData;
use App\DataTransferObjects\Auth\RegisterUserData;
use App\DataTransferObjects\Tokens\CreateTokenData;
use App\Enums\AuthAuditEvent;
use App\Events\AuthEventOccurred;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthenticatedUserResource;
use App\Http\Resources\PersonalAccessTokenResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\ValidatedInput;

/**
 * Registers a new User and issues the first Sanctum token.
 *
 * @example
 * POST /api/register {"name": "Alice", "email": "alice@example.com", "password": "SecretPass12", "password_confirmation": "SecretPass12"}
 */
final class RegisterController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Create an account and return a bearer token.
     *
     * @param  RegisterRequest                 $request    the validated registration request
     * @param  RegisterUserAction              $register   the registration Action
     * @param  CreatePersonalAccessTokenAction $issueToken the token issuance Action
     * @return JsonResponse                    the standardised success envelope
     */
    public function __invoke(
        RegisterRequest $request,
        RegisterUserAction $register,
        CreatePersonalAccessTokenAction $issueToken,
    ): JsonResponse {
        /*
        |--------------------------------------------------------------------------
        | Input
        |--------------------------------------------------------------------------
        */

        $input = $request->safe();

        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        |
        | Each step consumes the previous one's result, so the payloads are built
        | inline rather than hoisted into Input: the token needs the new User, and
        | the audit entry needs the issued token.
        |
        */

        $user = $register->execute(new RegisterUserData(
            name: $input->string('name')->toString(),
            email: $input->string('email')->toString(),
            password: $input->string('password')->toString(),
        ));

        $newToken = $issueToken->execute(new CreateTokenData(
            forUser: $user,
            name: $this->deviceName($input),
            abilities: ['*'],
        ));

        AuthEventOccurred::dispatch(new RecordAuthAuditData(
            event: AuthAuditEvent::Register,
            userId: $user->id,
            email: $user->email,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            personalAccessTokenId: $newToken->accessToken->id,
        ));

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return ApiResponse::success(
            data: [
                'user' => new AuthenticatedUserResource($user),
                'token' => new PersonalAccessTokenResource($newToken->accessToken),
                'plain_text_token' => $newToken->plainTextToken,
            ],
            message: 'Account Created Successfully',
            statusCode: 201,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Resolve the Sanctum token label from validated input.
     *
     * @param  \Illuminate\Support\ValidatedInput $input the validated request payload
     * @return string                             the device label
     */
    private function deviceName(ValidatedInput $input): string
    {
        if ($input->filled('device_name')) {
            return $input->string('device_name')->toString();
        }

        return 'API Session';
    }
}
