<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WebhookEndpointFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * An outbound webhook subscription owned by an Admin.
 *
 * @property int                             $id
 * @property int                             $user_id
 * @property string                          $name
 * @property string                          $url
 * @property list<string>                    $events
 * @property string                          $secret
 * @property bool                            $is_active
 * @property int                             $failure_streak
 * @property \Illuminate\Support\Carbon|null $disabled_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User                       $user the owning Admin
 */
final class WebhookEndpoint extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<WebhookEndpointFactory> */
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * Attributes an Admin may mass-assign for a webhook subscription.
     *
     * The endpoint form sets the subscription fields, while the delivery job updates `failure_streak` and
     * `disabled_at` as it observes failures; the key and timestamps stay framework-managed.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'url',
        'events',
        'secret',
        'is_active',
        'failure_streak',
        'disabled_at',
    ];

    /**
     * Attributes excluded from every array and JSON representation.
     *
     * The endpoint's HMAC signing secret is encrypted at rest and must never be serialised; hiding it here
     * keeps it out of API responses and log context even if a controller returns the model directly.
     *
     * @var list<string>
     */
    protected $hidden = [
        'secret',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    /**
     * Get the attribute casts for the model.
     *
     * The signing secret is encrypted at rest: unlike an API client secret
     * (verified by comparison and therefore hashable), the delivery job must
     * recover the plaintext on every send to compute the HMAC signature.
     *
     * @return array<string, string> a map of attribute name to cast type
     */
    protected function casts(): array
    {
        return [
            'events' => 'array',
            'secret' => 'encrypted',
            'is_active' => 'boolean',
            'failure_streak' => 'integer',
            'disabled_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the Admin that owns the endpoint.
     *
     * @return BelongsTo<User, $this> the User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }

    /**
     * Get the delivery attempts recorded for the endpoint.
     *
     * @return HasMany<WebhookDelivery, $this> the deliveries relationship
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
