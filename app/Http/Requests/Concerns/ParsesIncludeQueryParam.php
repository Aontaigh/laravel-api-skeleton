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
            'include' => ['sometimes', 'string'],
        ];
    }

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
