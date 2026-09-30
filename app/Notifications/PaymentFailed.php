<?php

namespace App\Notifications;

use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A renewal payment failed: access continues until the end of the grace period. */
class PaymentFailed extends Notification
{
    public function __construct(public string $business, public CarbonInterface $graceEnds) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->graceEnds->format('j M Y');

        return (new MailMessage)
            ->subject("Payment failed for {$this->business}")
            ->greeting('Hello '.strtok($notifiable->name, ' ').',')
            ->line("We could not take this month's payment for {$this->business}.")
            ->line("Please update your payment details by {$date}. Until then everything works as normal. After that date, access is paused until payment is made. Your records are kept.")
            ->action('Update payment details', route('app.settings'))
            ->line('Sign in, then open Settings and choose "Manage billing".');
    }
}
