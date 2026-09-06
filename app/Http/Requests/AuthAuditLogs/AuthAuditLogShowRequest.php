<?php

declare(strict_types=1);

namespace App\Http\Requests\AuthAuditLogs;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\AuthAuditLogs\AppliesAuthAuditLogShowParams;
use App\Models\AuthAuditLog;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises auth audit log show requests.
 */
final class AuthAuditLogShowRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesAuthAuditLogShowParams;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may view the route-bound audit log row
     */
    public function authorize(): bool
    {
        /** @var AuthAuditLog|null $authAuditLog */
        $authAuditLog = $this->route('auth_audit_log');

        return $authAuditLog instanceof AuthAuditLog
            && $this->user()?->can('view', $authAuditLog) === true;
    }

    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validation rules that apply to the request.
     *
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->authAuditLogShowRules();
    }

    /*
    |--------------------------------------------------------------------------
    | Validator Hooks
    |--------------------------------------------------------------------------
    */

    /**
     * Run allow-list validation for the request's query params.
     *
     * @param  Validator $validator the validator under extension
     * @return void
     */
    public function withValidator(Validator $validator): void
    {
        $this->validateAuthAuditLogShowParams($validator);
    }
}
