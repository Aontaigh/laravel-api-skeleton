<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email notification carrying the six-digit two-factor sign-in code.
 */
final class TwoFactorCodeNotification extends Notification
{
    /*
    |--------------------------------------------------------------------------
    | Constants
    |--------------------------------------------------------------------------
    */

    /** How long the emailed code stays valid, in minutes. */
    private static function expiresMinutes(): int
    {
        return (int) ceil(config()->integer('api.two_factor_code_ttl_seconds') / 60);
    }

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * @param string $code the plaintext six-digit one-time code
     */
    public function __construct(
        private readonly string $code,
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
     * Build the two-factor code mail message.
     *
     * @param  object      $notifiable the notifiable entity
     * @return MailMessage the mail message
     */
    public function toMail(object $notifiable): MailMessage
    {
        $brandName = config()->string('app.name');

        return (new MailMessage)
            ->subject('Your Two-Factor Code')
            ->line('Your '.$brandName.' sign-in code is: **'.$this->code.'**')
            ->line('This code expires in '.self::expiresMinutes().' minutes.')
            ->line('If you did not attempt to sign in, please ignore this email.');
    }
}
