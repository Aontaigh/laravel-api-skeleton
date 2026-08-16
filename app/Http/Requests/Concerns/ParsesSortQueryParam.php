<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\DataTransferObjects\IndexSort;
use App\Support\AllowList;
use App\Support\AllowListValidation;
use App\Support\IndexSortParser;
use Illuminate\Contracts\Validation\Validator;

/**
 * Parses and validates the `sort` query param against an allow-list.
 *
 * @mixin \App\Http\Requests\ApiFormRequest
 */
trait ParsesSortQueryParam
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

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
     * @return IndexSort    the resolved sort
     */
    public function indexSort(string $defaultColumn, string $defaultDirection = 'asc'): IndexSort
    {
        $sort = IndexSortParser::parse($this->safe()->string('sort')->toString());

        if ($sort->column === '') {
            return new IndexSort(column: $defaultColumn, direction: $defaultDirection);
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

            $column = IndexSortParser::parse($raw)->column;

            if ($column === '') {
                return;
            }

            $unknown = AllowList::unsupported([$column], $this->allowedSortColumns());

            if ($unknown !== []) {
                $allowed = $this->allowedSortColumns();

                $check->errors()->add(
                    'sort',
                    AllowListValidation::unsupportedMessage('Unsupported Sort Column', $unknown, $allowed),
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
}
