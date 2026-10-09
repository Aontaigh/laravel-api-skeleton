<?php

declare(strict_types=1);

namespace App\Queries\ApiClients;

use App\DataTransferObjects\ApiClients\ApiClientFilters;
use App\Models\ApiClient;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;

/**
 * Applies ApiClient filters to an Eloquent builder.
 */
final class ApiClientFilterQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Apply the Api Client filters to the query.
     *
     * @param  Builder<ApiClient> $query
     * @param  ApiClientFilters   $filters the validated filters
     * @return void
     */
    public function apply(Builder $query, ApiClientFilters $filters): void
    {
        if ($filters->search !== null) {
            $pattern = LikePattern::contains($filters->search);

            $query->whereRaw(
                LikePattern::containsWhereClause(ApiClientQueryConstraints::TABLE.'.name'),
                [$pattern],
            );
        }

        if ($filters->from !== null) {
            $query->where(ApiClientQueryConstraints::TABLE.'.created_at', '>=', $filters->from);
        }

        if ($filters->to !== null) {
            $query->where(ApiClientQueryConstraints::TABLE.'.created_at', '<=', $filters->to);
        }
    }
}
