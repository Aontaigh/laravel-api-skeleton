<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\DataTransferObjects\ListSort;
use App\Support\AllowListValidation;
use Illuminate\Contracts\Validation\Validator;

/**
 * Parses and validates the `sort` query param against an allow-list.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait ParsesSortQueryParam
{
    use ReadsRequestInput;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Get the normalised sort column and direction.
     *
     * Both defaults are parameters, and the column deliberately has no
     * default value: this trait is shared by every resource, so a baked-in
     * default column would order the next resource by a column its table
     * may not even have.
     *
     * @param  string       $defaultColumn    the column to use when `sort` is omitted
     * @param  'asc'|'desc' $defaultDirection the direction to use when `sort` is omitted
     * @return ListSort     the resolved sort
     */
    public function listSort(string $defaultColumn, string $defaultDirection = 'asc'): ListSort
    {
        $sort = $this->parseSortInput($this->safe()->string('sort')->toString());

        if ($sort->column === '') {
            return new ListSort(column: $defaultColumn, direction: $defaultDirection);
        }

        return $sort;
    }

    /*
    |--------------------------------------------------------------------------
    | Protected
    |--------------------------------------------------------------------------
    */

    /**
     * Validation rules for the sort query param.
     *
     * @return array<string, array<int, string>> the sort param rules
     */
    protected function sortQueryParamRules(): array
    {
        return [
            'sort' => ['sometimes', 'string'],
        ];
    }

    /**
     * Reject sort columns outside the resource allow-list.
     *
     * @param  Validator $validator the validator under extension
     * @return void      adds an error when `sort` names an unknown column
     */
    protected function validateSortQueryParam(Validator $validator): void
    {
        $validator->after(function (Validator $check): void {
            $raw = $this->input('sort');

            if (! is_string($raw)) {
                return;
            }

            $column = $this->parseSortInput($raw)->column;

            if ($column === '') {
                return;
            }

            if (! in_array($column, $this->allowedSortColumns(), true)) {
                $allowed = $this->allowedSortColumns();

                $check->errors()->add(
                    'sort',
                    AllowListValidation::unsupportedMessage('Unsupported Sort Column', [$column], $allowed),
                );
                $this->recordAllowListHint('sort', $allowed);
            }
        });
    }

    /**
     * Columns callers may sort on via `?sort=` (optionally `-` prefixed).
     *
     * @return list<string> the allowed sort columns
     */
    abstract protected function allowedSortColumns(): array;

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Split a raw `sort` value into its column and direction.
     *
     * Strips exactly one leading `-` so a malformed `--name` fails the
     * allow-list instead of silently resolving to `name`.
     *
     * @param  string   $raw the raw `sort` query param
     * @return ListSort the parsed sort, with an empty column when `sort` is blank
     */
    private function parseSortInput(string $raw): ListSort
    {
        $trimmed = trim($raw);
        $isDescending = str_starts_with($trimmed, '-');

        return new ListSort(
            column: $isDescending ? substr($trimmed, 1) : $trimmed,
            direction: $isDescending ? 'desc' : 'asc',
        );
    }
}
