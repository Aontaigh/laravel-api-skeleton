<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\AllowList;
use App\Support\AllowListValidation;
use App\Support\CommaSeparatedList;
use Illuminate\Contracts\Validation\Validator;

/**
 * Parses and validates the `include` query param against an allow-list.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait ParsesIncludeQueryParam
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use ReadsRequestInput;

    /*
    |--------------------------------------------------------------------------
    | Query Accessors
    |--------------------------------------------------------------------------
    */

    /**
     * Get the validated include keys.
     *
     * @return list<string> whitelisted relation names to eager-load
     */
    public function includes(): array
    {
        return AllowList::supported(
            CommaSeparatedList::parse($this->safe()->string('include')->toString()),
            $this->allowedIncludeKeys(),
        );
    }
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for the include query param.
     *
     * @return array<string, array<int, string>> the include param rules
     */
    protected function includeQueryParamRules(): array
    {
        return [
            'include' => ['sometimes', 'string', 'max:'.self::MAX_RAW_LENGTH],
        ];
    }

    /**
     * The largest raw `include` value these bounds permit.
     *
     * Standard: [OWASP API4:2023 Unrestricted Resource Consumption](https://owasp.org/API-Security/editions/2023/en/0xa4-unrestricted-resource-consumption/) ("Define and enforce
a maximum size of data on all incoming parameters and payloads, such as maximum length for
     * strings"), which traces to
     * [CWE-770](https://cwe.mitre.org/data/definitions/770.html) (Allocation of Resources Without
     * Limits or Throttling). A list param with no length ceiling is the cheapest denial-of-service
     * to write: one request carrying megabytes of commas forces the server to allocate and walk the
     * whole payload before any allow-list check rejects it.
     *
     * Why 255, and why a length rule at all when the comma-list filter rules deliberately omit one:
     *
     * - The size is chosen from the data, not from taste. `include` is intersected against
     *   `ALLOWED_INCLUDES`, which holds one to three relation names per resource, so the longest
     *   legal value is well under a hundred characters. 255 leaves headroom for a longer relation
     *   name without approaching anything a client would legitimately send.
     * - This bound is *not* in tension with the comma-list filter rules, which cap the **count** of
     *   values instead. Those filters validate every part against a closed enum, so an oversized
     *   value is rejected part-by-part and the count cap is a complete bound on the work. `include`
     *   has no such enum: it is a list of column-like tokens with no closed vocabulary, so counting
     *   values proves nothing about payload size and a length ceiling is the only bound available.
     * - The bound is enforced before `CommaSeparatedList::parse()` runs, so a padded value is
     *   rejected at validation rather than after parsing.
     *
     * Truncation is never an option here: silently dropping the tail of a fieldset produces a
     * response that looks complete but is not, so an oversized value is rejected instead.
     *
     * @see self::fieldsQueryParamRules() for the same bound on `fields[…]`
     */
    private const MAX_RAW_LENGTH = 255;

    /*
    |--------------------------------------------------------------------------
    | Allow-list Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Reject include keys outside the resource allow-list.
     *
     * @param  Validator $validator the validator under extension
     * @return void      adds an error when `include` names an unknown relation
     */
    protected function validateIncludeQueryParam(Validator $validator): void
    {
        $validator->after(function (Validator $check): void {
            $raw = $this->input('include');

            if (! is_string($raw)) {
                return;
            }

            $unknown = AllowList::unsupported(
                CommaSeparatedList::parse($raw),
                $this->allowedIncludeKeys(),
            );

            if ($unknown !== []) {
                $allowed = $this->allowedIncludeKeys();

                $check->errors()->add(
                    'include',
                    AllowListValidation::unsupportedMessage('Unsupported Include', $unknown, $allowed),
                );
                $this->recordAllowListHint('include', $allowed);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-lists
    |--------------------------------------------------------------------------
    */

    /**
     * Relation keys callers may request via `?include=`.
     *
     * @return list<string> the allowed include keys
     */
    abstract protected function allowedIncludeKeys(): array;
}
