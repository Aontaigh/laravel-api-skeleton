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

    public function withValidator(Validator $validator): void
    {
        $this->validateAuthAuditLogShowParams($validator);
    }
}
