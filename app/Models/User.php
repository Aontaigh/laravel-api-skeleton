<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MfaMethod;
use App\Notifications\Auth\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Arr;
use Laravel\Sanctum\HasApiTokens;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\TransientToken;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;
use UnitEnum;

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
 * @property MfaMethod|null                  $mfa_method
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
        'mfa_method',
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
            'mfa_method' => MfaMethod::class,
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
     * Whether the User holds the given abilities under both Spatie permissions
     * and the current Sanctum Token scope.
     *
     * Spatie permissions are resolved first through the parent gate. When the
     * request authenticated with a scoped Personal Access Token (no `*`
     * ability), every bare permission checked must also appear in the Token's
     * abilities - a Token issued for `roles.list` must not inherit the
     * Service role's other permissions. Session-cookie authentication (no
     * current Token) and wildcard Tokens are unaffected.
     *
     * @param  string|iterable<mixed>|UnitEnum $abilities the ability or abilities to check, or a comma-separated string
     * @param  mixed                           $arguments the Policy arguments (route-bound model or class name)
     * @return bool                            true when every ability passes both the Spatie and Token checks
     */
    public function can($abilities, $arguments = []): bool
    {
        if (! parent::can($abilities, $arguments)) {
            return false;
        }

        $token = $this->currentAccessToken();

        if ($this->isTokenUnrestricted($token)) {
            return true;
        }

        /*
         * Verb-style checks (`can('viewAny', User::class)`) resolve through a
         * Policy, whose nested `$user->can('users.list')` calls return here
         * with bare permission strings, where the Token scope is enforced.
         * Demanding the verb itself on the Token would require Gate verbs no
         * caller ever holds, so argument-backed checks defer to those nested
         * permission checks.
         */
        if ($arguments !== []) {
            return true;
        }

        $requested = is_string($abilities)
            ? array_map('trim', explode(',', $abilities))
            : Arr::wrap($abilities);

        foreach ($requested as $ability) {
            if (is_string($ability) && ! $token->can($ability)) {
                return false;
            }
        }

        return true;
    }

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
     * Whether this User has enrolled in multi-factor authentication.
     *
     * @return bool true when an MFA channel is configured
     */
    public function hasMfaEnabled(): bool
    {
        return $this->mfa_method !== null;
    }

    /**
     * Invalidate every existing web session for this User.
     *
     * Bumping the version makes every session stamped with the old value fail
     * the `session.version` gate on its next request - regardless of the
     * session driver - so a credential change or force-logout signs the User
     * out everywhere.
     *
     * @return void
     */
    public function rotateSessions(): void
    {
        $this->increment('session_version');
    }

    /**
     * Send the password reset link through the queued app notification.
     *
     * The framework default would emit its own mail pointing at the API host,
     * which only serves JSON. The app notification carries a config-driven
     * SPA destination (`api.password_reset_url`) instead.
     *
     * @param  string $token the password reset token issued by the broker
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the request's Token grants unrestricted access.
     *
     * `currentAccessToken()` is non-null only on Token-backed requests: a
     * cookie-session request has none, and Sanctum's guard hands `actingAs`
     * callers an always-permissive TransientToken. Only a persisted Personal
     * Access Token can answer `false` and reach the scope check.
     *
     * @param  PersonalAccessToken|TransientToken|null $token the Token issued for the current request, or null
     * @return bool                                    true when no Token scope needs enforcing
     */
    private function isTokenUnrestricted(PersonalAccessToken|TransientToken|null $token): bool
    {
        return $token?->can('*') !== false;
    }
}
