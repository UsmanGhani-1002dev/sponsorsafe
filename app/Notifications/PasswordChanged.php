<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent after Change password, so the person knows if someone else changed it. */
class PasswordChanged extends Notification
{
    public function __construct(public bool $superAdmin = false) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Your '.config('app.name').' password was changed')
            ->greeting('Hello '.strtok($notifiable->name, ' ').',')
            ->line('The password for '.$notifiable->email.' was changed on '.now('Europe/London')->format('j M Y \a\t H:i').' (UK time).')
            ->line('Any other devices signed in with this login have been signed out.');

        return $this->superAdmin
            ? $mail->line('If this was not you, ask another super admin to remove this login straight away, or reset it on the server with php artisan ops:create-admin.')
            : $mail->line('If this was not you, choose a new password now with "Forgot password?" on the sign-in page, and tell your admin.')
                ->action('Go to sign in', route('login'));
    }
}
