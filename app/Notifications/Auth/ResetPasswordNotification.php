<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Queued mail carrying the password reset link.
 *
 * The link destination is config-driven (`api.password_reset_url`) so a
 * separate SPA origin can host the reset form. The API host only serves
 * JSON, so falling back to `url('/reset-password')` is a development-only
 * convenience, never the production shape.
 */
final class ResetPasswordNotification extends Notification implements ShouldQueue
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
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new ResetPasswordNotification.
     *
     * @param string $token the password reset token issued by the broker
     */
    public function __construct(
        private readonly string $token,
    ) {}

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
     * Build the password reset mail message.
     *
     * @param  User        $notifiable the notifiable User
     * @return MailMessage the mail message
     */
    public function toMail(User $notifiable): MailMessage
    {
        $brandName = config()->string('app.name');

        return (new MailMessage)
            ->subject('Reset Your Password')
            ->line('A password reset was requested for your '.$brandName.' account.')
            ->line('This link expires in '.self::expiresMinutes().' minutes.')
            ->action('Reset Password', $this->resetUrl($notifiable))
            ->line('If you did not request a reset, no action is needed - your password will not change.');
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Get the reset link validity window in minutes.
     *
     * @return int the reset-link validity window in minutes
     */
    private static function expiresMinutes(): int
    {
        return config()->integer('auth.passwords.users.expire', 60);
    }

    /**
     * Build the reset-page URL carrying the token and email.
     *
     * @param  CanResetPassword $notifiable the notifiable entity
     * @return string           the reset URL
     */
    private function resetUrl(CanResetPassword $notifiable): string
    {
        $base = config('api.password_reset_url');
        $base = is_string($base) && $base !== '' ? $base : url('/reset-password');

        return $base.'?'.http_build_query([
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
