<?php

declare(strict_types=1);

namespace App\Queries\Webhooks;

use App\DataTransferObjects\Webhooks\WebhookDeliveryFilters;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Builder;

/**
 * Composes validated filters onto a delivery query scoped to one endpoint.
 */
final class WebhookDeliveryFilterQuery
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Apply the endpoint scope and filter constraints to the query.
     *
     * @param  Builder<WebhookDelivery> $query    the delivery query builder
     * @param  WebhookEndpoint          $endpoint the endpoint scoping the index
     * @param  WebhookDeliveryFilters   $filters  the validated filter DTO
     * @return void
     */
    public function apply(Builder $query, WebhookEndpoint $endpoint, WebhookDeliveryFilters $filters): void
    {
        $query->where('webhook_endpoint_id', $endpoint->id);

        if ($filters->events !== []) {
            $query->whereIn('event', $filters->events);
        }

        if ($filters->statuses !== []) {
            $query->whereIn('status', $filters->statuses);
        }

        if ($filters->from !== null) {
            $query->where('created_at', '>=', $filters->from);
        }

        if ($filters->to !== null) {
            $query->where('created_at', '<=', $filters->to);
        }
    }
}
