<?php

declare(strict_types=1);

namespace App\Notifications\Auth;

use App\Enums\PasswordChangeSource;
use App\Models\User;
use App\Services\UserAgent\Contracts\UserAgentParser;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Queued security alert sent after a password change succeeds.
 *
 * Dispatch is best-effort by the caller: once the password is already saved,
 * a queueing failure must be logged, never surfaced as a failed request.
 */
final class PasswordChangedNotification extends Notification implements ShouldQueue
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

    /**
     * When the password change succeeded, fixed at dispatch.
     */
    private readonly Carbon $changedAt;

    /*
    |--------------------------------------------------------------------------
    | Constructor
    |--------------------------------------------------------------------------
    */

    /**
     * Create a new PasswordChangedNotification.
     *
     * @param PasswordChangeSource $source          where the password change originated
     * @param string|null          $ipAddress       the client IP at change time
     * @param string|null          $userAgent       the raw user-agent header value
     * @param Carbon|null          $changedAt       when the change succeeded; defaults to now
     * @param UserAgentParser      $userAgentParser the configured parser driver, injected by the caller
     */
    public function __construct(
        public readonly PasswordChangeSource $source,
        private readonly ?string $ipAddress = null,
        private readonly ?string $userAgent = null,
        ?Carbon $changedAt = null,
        private readonly UserAgentParser $userAgentParser = new \App\Services\UserAgent\BasicUserAgentParser,
    ) {
        $this->changedAt = $changedAt ?? Carbon::now();
    }

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
     * Build the password-changed security alert mail.
     *
     * @param  User        $notifiable the notifiable User
     * @return MailMessage the mail message
     */
    public function toMail(User $notifiable): MailMessage
    {
        $brandName = config()->string('app.name');
        $timezone = config()->string('app.timezone');
        $changedAt = $this->changedAt->copy()->setTimezone($timezone !== '' ? $timezone : 'UTC');
        $parsed = $this->userAgentParser->parse($this->userAgent);

        return (new MailMessage)
            ->subject('Your Password Was Changed')
            ->line('The password on your '.$brandName.' account was changed via '.$this->source->label().' at '.$changedAt->format('j F Y, H:i:s T').'.')
            ->line('Request details: IP '.$this->displayOrUnknown($this->ipAddress).', browser '.$this->displayOrUnknown($parsed->browser).' on '.$this->displayOrUnknown($parsed->platform).'.')
            ->line('If this was you, no action is needed.')
            ->action('Reset Your Password', $this->forgotUrl())
            ->line('If you did not change your password, reset it immediately and contact support.');
    }

    /*
    |--------------------------------------------------------------------------
    | Private
    |--------------------------------------------------------------------------
    */

    /**
     * Format an optional request detail for display.
     *
     * @param  string|null $value the raw detail value
     * @return string      the value, or `Unknown` when missing
     */
    private function displayOrUnknown(?string $value): string
    {
        return $value !== null && $value !== '' ? $value : 'Unknown';
    }

    /**
     * Build the forgot-password page URL for the "not you" path.
     *
     * Config-driven for the same reason as the reset link: a separate SPA
     * origin hosts the password screens.
     *
     * @return string the forgot-password URL
     */
    private function forgotUrl(): string
    {
        $base = config('api.password_forgot_url');
        $base = is_string($base) && $base !== '' ? $base : url('/forgot-password');

        return $base;
    }
}
