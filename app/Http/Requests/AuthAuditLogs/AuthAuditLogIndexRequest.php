<?php

declare(strict_types=1);

namespace App\Http\Requests\AuthAuditLogs;

use App\Http\Requests\ApiFormRequest;
use App\Http\Requests\Concerns\AuthAuditLogs\AppliesAuthAuditLogFilters;
use App\Models\AuthAuditLog;
use Illuminate\Contracts\Validation\Validator;

/**
 * Validates and authorises auth audit log index requests.
 */
final class AuthAuditLogIndexRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use AppliesAuthAuditLogFilters;

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may list auth audit logs
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AuthAuditLog::class) === true;
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
        return $this->authAuditLogFilterRules();
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
        $this->validateFilterKeys($validator);
        $this->validateFieldsKeys($validator);
        $this->validateFieldsQueryParam($validator, 'auth_audit_logs');
        $this->validateFieldsQueryParam($validator, 'users');
        $this->validateSortQueryParam($validator);
        $this->validateIncludeQueryParam($validator);
    }
}
