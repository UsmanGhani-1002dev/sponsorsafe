<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The subscription is suspended (grace period over, or the subscription ended). Data is kept. */
class AccessPaused extends Notification
{
    public function __construct(public string $business, public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Access paused for {$this->business}")
            ->greeting('Hello '.strtok($notifiable->name, ' ').',')
            ->line($this->reason === Business::SUSPENDED_CANCELLED
                ? "The subscription for {$this->business} has ended, so access is now paused."
                : "We still could not take payment for {$this->business}, so access is now paused.")
            ->line('Your records are kept safely. To restore access, reply to this email or contact us at '.config('sponsorsafe.support_email').'.');
    }
}
