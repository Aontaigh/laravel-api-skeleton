<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

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
    use ReadsRequestInput;

    /*
    |--------------------------------------------------------------------------
    | Public
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

        return array_values(array_intersect(
            $requested,
            $this->allowedFieldsFor($resourceKey),
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Protected
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
            "fields.{$resourceKey}" => ['sometimes', 'string'],
        ];
    }

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

            $unknown = array_diff(
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

            $unknown = array_diff(array_keys($fields), $this->allowedFieldsResourceKeys());

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
