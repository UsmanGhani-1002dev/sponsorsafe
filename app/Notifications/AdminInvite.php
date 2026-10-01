<?php

namespace App\Notifications;

use App\Services\PasswordLinks;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Another admin added in Settings → Admin logins: set a password, then add an authenticator app. */
class AdminInvite extends Notification
{
    public function __construct(public string $token, public string $business, public string $invitedBy) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You've been added as an admin for {$this->business}")
            ->greeting('Hello '.strtok($notifiable->name, ' ').',')
            ->line("{$this->invitedBy} has added you as an admin for {$this->business} on ".config('app.name').'.')
            ->line('Admins keep the business\'s sponsor licence records up to date and see when something must be reported to the Home Office.')
            ->action('Set your password', route('password.set', $this->token))
            ->line('When you first sign in you will add an authenticator app (such as Google or Microsoft Authenticator). This link works once and expires in '.PasswordLinks::INVITE_DAYS.' days.');
    }
}
