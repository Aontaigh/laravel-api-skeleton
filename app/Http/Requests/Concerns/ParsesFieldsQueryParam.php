<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Support\AllowList;
use App\Support\AllowListValidation;
use App\Support\CommaSeparatedList;
use Illuminate\Contracts\Validation\Validator;

/**
 * Parses and validates `fields[{resource}]` sparse fieldsets against allow-lists.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait ParsesFieldsQueryParam
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
     * Get validated sparse fieldset columns for a resource, or null when omitted.
     *
     * @param  string            $resourceKey the `fields[…]` key
     * @return list<string>|null whitelisted column names, or null for full row
     */
    public function fieldsFor(string $resourceKey): ?array
    {
        if (! $this->safe()->filled("fields.{$resourceKey}")) {
            return null;
        }

        $requested = CommaSeparatedList::parse(
            $this->safe()->string("fields.{$resourceKey}")->toString(),
        );

        if ($requested === []) {
            return null;
        }

        return AllowList::supported(
            $requested,
            $this->allowedFieldsFor($resourceKey),
        );
    }
    /*
    |--------------------------------------------------------------------------
    | Validation Rules
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for a sparse fieldset query param.
     *
     * @param  string                            $resourceKey the `fields[…]` key (e.g. `users`, `teams`)
     * @return array<string, array<int, string>> the fields param rules
     */
    protected function fieldsQueryParamRules(string $resourceKey): array
    {
        return [
            "fields.{$resourceKey}" => ['sometimes', 'string', 'max:'.self::MAX_RAW_LENGTH],
        ];
    }

    /**
     * The largest raw `fields[…]` value these bounds permit.
     *
     * Standard: [OWASP API4:2023 Unrestricted Resource Consumption](https://owasp.org/API-Security/editions/2023/en/0xa4-unrestricted-resource-consumption/) ("Define and enforce
a maximum size of data on all incoming parameters and payloads, such as maximum length for
     * strings"), which traces to
     * [CWE-770](https://cwe.mitre.org/data/definitions/770.html). Same rationale as the `include`
     * bound in `ParsesIncludeQueryParam`: an unmeasured list param is an unbounded allocation
     * before any allow-list intersection runs.
     *
     * Why 255, measured rather than guessed: the widest allow-list in this app is
     * `AuthAuditLogQueryConstraints::ALLOWED_FIELDS`, 16 fields that join to 198 characters. 255
     * leaves headroom for a longer column name without approaching anything a caller would
     * legitimately send, and each `fields[…]` key is bounded independently, so one request may
     * still carry several keys at once.
     *
     * Re-measure both numbers when a column is added to any `ALLOWED_FIELDS` list: this bound is
     * sized from that measurement, so a silent column addition is exactly how it would come to
     * reject a legitimate full fieldset.
     *
     * This is a request-size bound rather than a list-size bound, and it does not conflict with the
     * comma-list filter rules capping the **count** of values. A filter part is validated against a
     * closed enum, so a padded list is rejected part-by-part and the count cap already bounds the
     * work. A fieldset part is a bare column name with no closed vocabulary, so there is nothing to
     * count against and a length ceiling is the only bound that applies.
     *
     * Truncating instead of rejecting would be worse than a slow request: a silently shortened
     * fieldset returns a response that looks complete but is missing the columns the caller asked
     * for, which is a correctness failure they cannot detect.
     *
     * @see \App\Http\Requests\Concerns\ParsesIncludeQueryParam::MAX_RAW_LENGTH for the `include` bound
     */
    private const MAX_RAW_LENGTH = 255;

    /*
    |--------------------------------------------------------------------------
    | Allow-list Validation
    |--------------------------------------------------------------------------
    */

    /**
     * Reject sparse fieldset keys outside the resource allow-list.
     *
     * @param  Validator $validator   the validator under extension
     * @param  string    $resourceKey the `fields[…]` key being validated
     * @return void      adds an error when a field name is not whitelisted
     */
    protected function validateFieldsQueryParam(Validator $validator, string $resourceKey): void
    {
        $validator->after(function (Validator $check) use ($resourceKey): void {
            $raw = $this->input("fields.{$resourceKey}");

            if (! is_string($raw)) {
                return;
            }

            $unknown = AllowList::unsupported(
                CommaSeparatedList::parse($raw),
                $this->allowedFieldsFor($resourceKey),
            );

            if ($unknown !== []) {
                $allowed = $this->allowedFieldsFor($resourceKey);

                $check->errors()->add(
                    "fields.{$resourceKey}",
                    AllowListValidation::unsupportedMessage('Unsupported Field', $unknown, $allowed),
                );
                $this->recordAllowListHint("fields.{$resourceKey}", $allowed);
            }
        });
    }

    /**
     * Reject unknown top-level keys inside `fields[…]`.
     *
     * @param  Validator $validator the validator under extension
     * @return void      adds an error for each unsupported `fields[…]` resource key
     */
    protected function validateFieldsKeys(Validator $validator): void
    {
        $validator->after(function (Validator $check): void {
            /** @var mixed $fields */
            $fields = $this->input('fields', []);

            if (! is_array($fields)) {
                return;
            }

            $unknown = AllowList::unsupported(
                array_keys($fields),
                $this->allowedFieldsResourceKeys(),
            );

            if ($unknown !== []) {
                $this->recordAllowListHint('fields', $this->allowedFieldsResourceKeys());
            }

            foreach ($unknown as $key) {
                $check->errors()->add(
                    "fields.{$key}",
                    AllowListValidation::unsupportedMessage(
                        'Unsupported Fields Resource',
                        [$key],
                        $this->allowedFieldsResourceKeys(),
                    ),
                );
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Allow-lists
    |--------------------------------------------------------------------------
    */

    /**
     * Resource keys callers may use under `fields[…]`.
     *
     * @return list<string> allowed `fields` keys (e.g. `users`, `teams`)
     */
    abstract protected function allowedFieldsResourceKeys(): array;

    /**
     * Columns callers may request for a given `fields[…]` resource key.
     *
     * @param  string       $resourceKey the `fields[…]` key
     * @return list<string> whitelisted column names for that resource
     */
    abstract protected function allowedFieldsFor(string $resourceKey): array;
}
