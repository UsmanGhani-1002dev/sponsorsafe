<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The daily reminder email: everything newly due for one business, in one message. */
class ComplianceDigest extends Notification
{
    /** @param list<array{text: string, href: string}> $items */
    public function __construct(public string $business, public array $items) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $n = count($this->items);
        $mail = (new MailMessage)
            ->subject($n === 1 ? "1 compliance reminder for {$this->business}" : "{$n} compliance reminders for {$this->business}")
            ->greeting('Hello '.strtok($notifiable->name, ' ').',')
            ->line($n === 1 ? 'This needs your attention:' : 'These need your attention:');

        foreach ($this->items as $item) {
            $mail->line('- '.$item['text']);
        }

        return $mail
            ->action('Open your dashboard', route('app.dashboard'))
            ->line('You only get each reminder once. Change when reminders are sent in Settings → Compliance rules.');
    }
}
