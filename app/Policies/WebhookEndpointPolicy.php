<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;

/**
 * Authorisation rules for webhook endpoint management.
 */
final class WebhookEndpointPolicy
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Whether the User may list webhook endpoints.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may view the endpoint index
     */
    public function viewAny(User $user): bool
    {
        return $user->can('webhooks.list');
    }

    /**
     * Whether the User may view a single webhook endpoint.
     *
     * @param  User            $user     the authenticated User
     * @param  WebhookEndpoint $endpoint the endpoint being viewed
     * @return bool            true when the User may view that endpoint
     */
    public function view(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->can('webhooks.list');
    }

    /**
     * Whether the User may create webhook endpoints.
     *
     * @param  User $user the authenticated User
     * @return bool true when the User may create an endpoint
     */
    public function create(User $user): bool
    {
        return $user->can('webhooks.create');
    }

    /**
     * Whether the User may update the given webhook endpoint.
     *
     * @param  User            $user     the authenticated User
     * @param  WebhookEndpoint $endpoint the endpoint being updated
     * @return bool            true when the User may update that endpoint
     */
    public function update(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->can('webhooks.update');
    }

    /**
     * Whether the User may delete the given webhook endpoint.
     *
     * @param  User            $user     the authenticated User
     * @param  WebhookEndpoint $endpoint the endpoint being deleted
     * @return bool            true when the User may delete that endpoint
     */
    public function delete(User $user, WebhookEndpoint $endpoint): bool
    {
        return $user->can('webhooks.delete');
    }
}
