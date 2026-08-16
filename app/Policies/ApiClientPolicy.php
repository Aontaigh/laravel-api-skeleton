<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ApiClient;
use App\Models\User;

/**
 * Authorisation rules for API client management.
 */
final class ApiClientPolicy
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the User may list API clients.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may view the client index
     */
    public function viewAny(User $user): bool
    {
        return $user->can('api-clients.list');
    }

    /**
     * Whether the User may view a single API client.
     *
     * @param User      $user   the authenticated User
     * @param ApiClient $client the client being viewed
     */
    public function view(User $user, ApiClient $client): bool
    {
        return $user->can('api-clients.list');
    }

    /**
     * Whether the User may create API clients.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may create a client
     */
    public function create(User $user): bool
    {
        return $user->can('api-clients.create');
    }

    /**
     * Whether the User may revoke the given API client.
     *
     * @param  User      $user   the authenticated User
     * @param  ApiClient $client the client being revoked
     * @return bool      true when the User may revoke that client
     */
    public function delete(User $user, ApiClient $client): bool
    {
        return $user->can('api-clients.delete');
    }

    /**
     * Whether the User may update the given API client.
     *
     * @param  User      $user   the authenticated User
     * @param  ApiClient $client the client being updated
     * @return bool      true when the User may update that client
     */
    public function update(User $user, ApiClient $client): bool
    {
        return $user->can('api-clients.update');
    }
}
