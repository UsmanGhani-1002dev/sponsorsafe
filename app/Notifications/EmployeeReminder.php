<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** An employee's own reminders (new visa and share code, documents HR asked for), in one email. */
class EmployeeReminder extends Notification
{
    /** @param list<array{title: string, text: string, href: string}> $items */
    public function __construct(public string $business, public array $items) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $n = count($this->items);
        $mail = (new MailMessage)
            ->subject($n === 1 ? $this->items[0]['title'] : "{$n} things {$this->business} needs from you")
            ->greeting('Hello '.strtok($notifiable->name, ' ').',');

        foreach ($this->items as $item) {
            $mail->line($n === 1 ? $item['text'] : '- '.$item['text']);
        }

        return $mail
            ->action('Open your portal', $n === 1 ? $this->items[0]['href'] : route('portal.home'))
            ->line('First time signing in? Use the invite email from '.$this->business.', or "Forgot password?" on the sign-in page.')
            ->line('If you have already sent this, you can ignore this email.');
    }
}
