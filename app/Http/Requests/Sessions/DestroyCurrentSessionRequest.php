<?php

declare(strict_types=1);

namespace App\Http\Requests\Sessions;

use App\Http\Requests\ApiFormRequest;
use App\Models\WebSession;

/**
 * Authorises a request to revoke the caller's current browser session.
 */
final class DestroyCurrentSessionRequest extends ApiFormRequest
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the User is authorised to make this request.
     *
     * @return bool true when the User may revoke their own current session
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can('sessions.revoke-own')
            && ! $user->isServiceAccount();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>> no request body is accepted
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Resolve the registry row for the inbound Laravel session id.
     */
    public function currentWebSession(): ?WebSession
    {
        if (! $this->hasSession()) {
            return null;
        }

        return WebSession::query()
            ->where('user_id', $this->user()?->id)
            ->where('session_id', $this->session()->getId())
            ->whereNull('revoked_at')
            ->first();
    }
}
