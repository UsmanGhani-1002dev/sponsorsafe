<?php

namespace App\Notifications;

use App\Services\PasswordLinks;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordReset extends Notification
{
    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Reset your SponsorSafe password')
            ->greeting('Hello '.strtok($notifiable->name, ' ').',')
            ->line('Someone asked to reset the password for this account. If it was you, choose a new password here:')
            ->action('Choose a new password', route('password.set', $this->token))
            ->line('This link works once and expires in '.PasswordLinks::RESET_MINUTES.' minutes. If you did not ask for it, you can ignore this email.');
    }
}
