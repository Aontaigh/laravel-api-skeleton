<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuthAuditEvent;
use Database\Factories\AuthAuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persisted authentication audit event.
 *
 * @property int                             $id
 * @property int|null                        $user_id
 * @property AuthAuditEvent                  $event
 * @property string|null                     $email
 * @property string|null                     $ip_address
 * @property string|null                     $user_agent
 * @property int|null                        $personal_access_token_id
 * @property int|null                        $api_client_id
 * @property bool                            $remember_me
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class AuthAuditLog extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<AuthAuditLogFactory> */
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'event',
        'email',
        'ip_address',
        'user_agent',
        'personal_access_token_id',
        'api_client_id',
        'remember_me',
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
            'event' => AuthAuditEvent::class,
            'remember_me' => 'boolean',
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
     * Get the User associated with the audit event.
     *
     * @return BelongsTo<User, $this> the User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the API client linked to a client-credentials exchange.
     *
     * @return BelongsTo<ApiClient, $this>
     */
    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }
}
