<?php

declare(strict_types=1);

namespace App\Queries\Webhooks;

use App\DataTransferObjects\Webhooks\WebhookEndpointFilters;
use App\Models\WebhookEndpoint;
use App\Support\LikePattern;
use Illuminate\Database\Eloquent\Builder;

/**
 * Composes validated filters onto any webhook endpoint query builder.
 */
final class WebhookEndpointFilterQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Apply filter constraints to the query.
     *
     * @param  Builder<WebhookEndpoint> $query   the endpoint query builder
     * @param  WebhookEndpointFilters   $filters the validated filter DTO
     * @return void
     */
    public function apply(Builder $query, WebhookEndpointFilters $filters): void
    {
        if ($filters->search === null) {
            return;
        }

        $pattern = LikePattern::contains($filters->search);

        $query->where(function (Builder $query) use ($pattern): void {
            $query->whereRaw(
                LikePattern::containsWhereClause(WebhookEndpointQueryConstraints::TABLE.'.name'),
                [$pattern],
            )->orWhereRaw(
                LikePattern::containsWhereClause(WebhookEndpointQueryConstraints::TABLE.'.url'),
                [$pattern],
            );
        });
    }
}
