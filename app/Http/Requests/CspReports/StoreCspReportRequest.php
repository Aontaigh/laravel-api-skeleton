<?php

declare(strict_types=1);

namespace App\Http\Requests\CspReports;

use App\Http\Requests\ApiFormRequest;

/**
 * Validates the request for storing a CSP violation report.
 *
 * Browsers send the report body as `application/csp-report` or
 * `application/reports+json`, neither of which Laravel parses as form data,
 * so the controller reads and decodes `$request->getContent()` directly
 * rather than through the validated input bag. This request class carries no
 * rules; it exists so the endpoint follows the same one-FormRequest-per-route
 * convention as every other endpoint and has a single place for future
 * authorisation or validation to land.
 */
final class StoreCspReportRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool always true; an anonymous browser sends this report, not a signed-in User
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed> an empty rule set; the raw body is read and parsed manually
     */
    public function rules(): array
    {
        return [];
    }
}
