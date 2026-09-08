<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Email;

use App\Actions\Auth\VerifyEmailAction;
use App\Http\Requests\Auth\VerifyEmailRequest;
use App\Support\RequestId;
use Illuminate\Http\RedirectResponse;

/**
 * Verifies a User's e-mail address from the temporary signed link, then
 * redirects the browser to the configured SPA result page.
 *
 * @example
 * GET /api/auth/email/verify/{id}/{hash}?expires=...&signature=...
 */
final class VerifyEmailController
{
    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Verify the e-mail address from the signed link.
     *
     * The `signed` middleware validates the HMAC signature and expiry. The
     * Action performs the mailbox compare and idempotent verification; every
     * failure mode answers with the same redirect carrying `verified=0` so a
     * tampered link is indistinguishable from a missing account to the
     * browser - while the audit trail records the attempt for the SOC.
     *
     * @param  VerifyEmailRequest $request the request (signed route)
     * @param  string             $id      the User id from the signed URL
     * @param  string             $hash    the e-mail hash from the signed URL
     * @return RedirectResponse   a redirect to the SPA result page
     */
    public function __invoke(
        VerifyEmailRequest $request,
        string $id,
        string $hash,
        VerifyEmailAction $action,
    ): RedirectResponse {
        /*
        |--------------------------------------------------------------------------
        | Action
        |--------------------------------------------------------------------------
        */

        $verified = $action->execute(
            id: $id,
            hash: $hash,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            requestId: RequestId::current($request),
        );

        /*
        |--------------------------------------------------------------------------
        | Response
        |--------------------------------------------------------------------------
        */

        return $this->redirectToSpa($verified);
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Redirect to the SPA verification-result page carrying the outcome.
     *
     * The destination is server config (`api.email_verification_url`) - never
     * a caller-supplied parameter, so no open-redirect surface exists.
     *
     * @param  bool             $verified whether verification succeeded
     * @return RedirectResponse the redirect response
     */
    private function redirectToSpa(bool $verified): RedirectResponse
    {
        $destination = config('api.email_verification_url');
        $base = is_string($destination) && $destination !== '' ? $destination : url('/verify-email');

        return redirect()->to($base.'?verified='.($verified ? '1' : '0'));
    }
}
