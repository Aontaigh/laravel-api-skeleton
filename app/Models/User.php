<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

/**
 * An authenticated User.
 *
 * @property int                             $id
 * @property int|null                        $team_id
 * @property string                          $name
 * @property string                          $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 * @property bool                            $is_service_account
 * @property \Illuminate\Support\Carbon|null $suspended_at
 * @property int                             $session_version
 * @property-read Collection<int, Role>      $roles the HasRoles::roles() relation,
 *                                                   declared here because that trait's
 *                                                   `BelongsToMany` return type carries
 *                                                   no generics for PHPStan to resolve
 */
final class User extends Authenticatable
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @var list<string> */
    protected $fillable = [
        'team_id',
        'name',
        'email',
        'password',
        'is_service_account',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_service_account' => 'boolean',
            'suspended_at' => 'datetime',
            'session_version' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Get the Team the User belongs to.
     *
     * @return BelongsTo<Team, $this> the Team relationship
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class)->withDefault();
    }

    /**
     * Get the API clients owned by this service account.
     *
     * @return HasMany<ApiClient, $this> the ApiClient relationship
     */
    public function apiClients(): HasMany
    {
        return $this->hasMany(ApiClient::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether this User is a non-interactive service account.
     *
     * @return bool true when the User backs an API Client rather than a person
     */
    public function isServiceAccount(): bool
    {
        return $this->is_service_account;
    }

    /**
     * Whether this User's account is suspended.
     *
     * A suspended account is rejected at the `active.account` gate on every
     * authenticated request, regardless of how it authenticated.
     *
     * @return bool true when the account has been suspended
     */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /**
     * Invalidate every existing web session for this User.
     *
     * Bumping the version makes every session stamped with the old value fail
     * the `session.version` gate on its next request — regardless of the
     * session driver — so a credential change or force-logout signs the User
     * out everywhere.
     */
    public function rotateSessions(): void
    {
        $this->increment('session_version');
    }
}
