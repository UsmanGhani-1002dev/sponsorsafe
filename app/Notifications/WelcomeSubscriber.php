<?php

namespace App\Notifications;

use App\Services\PasswordLinks;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** After the first payment: welcome, and a link to set the admin's password. */
class WelcomeSubscriber extends Notification
{
    public function __construct(public string $token, public string $business) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome to '.config('app.name').', set your password')
            ->greeting('Hello '.strtok($notifiable->name, ' ').',')
            ->line("{$this->business} is now subscribed. Thank you.")
            ->line('Set your password, then add an authenticator app (such as Google or Microsoft Authenticator) when you first sign in. After that, add your first employees in a few minutes.')
            ->action('Set your password', route('password.set', $this->token))
            ->line('This link works once and expires in '.PasswordLinks::INVITE_DAYS.' days. If it has expired, use "Forgot password" on the sign-in page.');
    }
}
