<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ApiClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Machine-to-machine API client credentials linked to a service User.
 *
 * @property int                             $id
 * @property int                             $user_id
 * @property string                          $name
 * @property string                          $client_id
 * @property string                          $client_secret
 * @property list<string>                    $abilities
 * @property bool                            $is_active
 * @property \Illuminate\Support\Carbon|null $last_used_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User                       $user the linked service account
 */
final class ApiClient extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<ApiClientFactory> */
    use HasFactory;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'name',
        'client_id',
        'client_secret',
        'abilities',
        'is_active',
        'last_used_at',
    ];

    /** @var list<string> */
    protected $hidden = [
        'client_secret',
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
            'abilities' => 'array',
            'is_active' => 'boolean',
            'last_used_at' => 'datetime',
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
     * Get the service User this client authenticates as.
     *
     * @return BelongsTo<User, $this> the User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
