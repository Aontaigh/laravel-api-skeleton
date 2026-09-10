<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single outbound webhook delivery attempt record.
 *
 * One row per subscribed endpoint per domain event; retries mutate the row
 * (attempts, next retry, last error) rather than inserting new ones, so the
 * deliveries index reads as the full history without pagination tricks.
 *
 * @property int                             $id
 * @property string                          $uuid
 * @property int                             $webhook_endpoint_id
 * @property string                          $event
 * @property array<string, mixed>            $payload
 * @property WebhookDeliveryStatus           $status
 * @property int                             $attempts
 * @property \Illuminate\Support\Carbon|null $next_retry_at
 * @property int|null                        $last_status_code
 * @property string|null                     $last_error
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read WebhookEndpoint            $endpoint the subscribed endpoint
 */
final class WebhookDelivery extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var list<string> */
    protected $fillable = [
        'uuid',
        'webhook_endpoint_id',
        'event',
        'payload',
        'status',
        'attempts',
        'next_retry_at',
        'last_status_code',
        'last_error',
        'delivered_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | `casts()`
    |--------------------------------------------------------------------------
    */

    /**
     * Get the attribute casts for the model.
     *
     * @return array<string, string> a map of attribute name to cast type
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookDeliveryStatus::class,
            'attempts' => 'integer',
            'next_retry_at' => 'datetime',
            'last_status_code' => 'integer',
            'delivered_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the endpoint the delivery was sent to.
     *
     * @return BelongsTo<WebhookEndpoint, $this> the endpoint relationship
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
