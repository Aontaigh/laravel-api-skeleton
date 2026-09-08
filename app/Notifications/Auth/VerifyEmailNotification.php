<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Queued mail carrying the temporary signed e-mail verification link.
 *
 * The link destination is config-driven (`api.email_verification_url`) so a
 * separate SPA origin can host the verification result page. The API host
 * only serves JSON, so falling back to `url('/verify-email')` is a
 * development-only convenience, never the production shape.
 */
final class VerifyEmailNotification extends Notification implements ShouldQueue
{
    /*
    |--------------------------------------------------------------------------
    | Traits
    |--------------------------------------------------------------------------
    */

    use Queueable;
    use SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /**
     * Number of times the notification may be attempted before failing.
     */
    public int $tries = 3;

    /**
     * Seconds the notification may run before timing out.
     */
    public int $timeout = 30;

    /*
    |--------------------------------------------------------------------------
    | Public
    |--------------------------------------------------------------------------
    */

    /**
     * Get the delivery channels.
     *
     * @param  object       $notifiable the notifiable entity
     * @return list<string> the channels
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the e-mail verification mail message.
     *
     * @param  User        $notifiable the notifiable User
     * @return MailMessage the mail message
     */
    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verify Your E-Mail Address')
            ->line('Confirm your e-mail address to activate your '.$notifiable->name.' account.')
            ->line('This link expires in '.self::expiresMinutes().' minutes.')
            ->action('Verify E-Mail Address', $this->verificationUrl($notifiable))
            ->line('If you did not create an account, no action is needed.');
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Get the verification link validity window in minutes.
     */
    private static function expiresMinutes(): int
    {
        return config()->integer('auth.verification.expire', 60);
    }

    /**
     * Build the temporary signed verification URL for the notifiable.
     *
     * Laravel's MustVerifyEmail contract uses `sha1(email)` as a checksum
     * beside the HMAC signature so the signed URL binds to that mailbox:
     * integrity comes from the signature, not from `sha1` as a secret. The
     * signed route targets the API, which validates the outcome and then
     * redirects the browser to the configured SPA result page - the
     * destination is server config (`api.email_verification_url`), never a
     * caller-supplied redirect parameter, so no open-redirect surface exists.
     *
     * @param  User   $notifiable the notifiable User
     * @return string the signed verification URL
     */
    private function verificationUrl(User $notifiable): string
    {
        return URL::temporarySignedRoute(
            'email.verification.verify',
            Carbon::now()->addMinutes(self::expiresMinutes()),
            [
                'id' => $notifiable->getKey(),
                // nosemgrep: php.lang.security.weak-crypto.weak-crypto
                'hash' => sha1($notifiable->getEmailForVerification()),
            ],
        );
    }
}
