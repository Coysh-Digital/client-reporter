<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "You've been given access" — sent when staff create an account and ask for
 * an invitation instead of typing a password. The link opens the set-password
 * screen and stays valid for a few days.
 */
class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $token,
        public readonly string $invitedBy,
        public readonly string $agencyName,
        public readonly ?string $clientName = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->email, 'invite' => 1]);
        $days = (int) ceil((int) config('auth.passwords.invites.expire', 4320) / 1440);

        $message = (new MailMessage)
            ->subject("You've been invited to {$this->agencyName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->invitedBy} has set up an account for you at {$this->agencyName}.");

        if ($this->clientName !== null) {
            $message->line("You'll be able to see the websites and reports for {$this->clientName}.");
        }

        return $message
            ->action('Choose your password', $url)
            ->line("This link works for {$days} ".($days === 1 ? 'day' : 'days').'. If it has expired, use “Forgot your password?” on the sign-in page to get a new one.')
            ->salutation("— {$this->agencyName}");
    }
}
