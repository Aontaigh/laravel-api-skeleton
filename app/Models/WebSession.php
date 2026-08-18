<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WebSessionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A registered cookie-bound web session for a User.
 *
 * @property int                             $id
 * @property int                             $user_id
 * @property string                          $session_id
 * @property string|null                     $device_name
 * @property string|null                     $ip_address
 * @property string|null                     $user_agent
 * @property bool                            $remember_me
 * @property \Illuminate\Support\Carbon|null $last_activity_at
 * @property \Illuminate\Support\Carbon|null $revoked_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
final class WebSession extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<WebSessionFactory> */
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'session_id',
        'device_name',
        'ip_address',
        'user_agent',
        'remember_me',
        'last_activity_at',
        'revoked_at',
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
            'remember_me' => 'boolean',
            'last_activity_at' => 'datetime',
            'revoked_at' => 'datetime',
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
     * Get the User who owns the web session.
     *
     * @return BelongsTo<User, $this> the User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the session has been revoked from the registry.
     */
    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
