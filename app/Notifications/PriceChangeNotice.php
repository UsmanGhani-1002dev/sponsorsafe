<?php

namespace App\Notifications;

use App\Support\Pricing;
use Carbon\CarbonInterface;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Sent 30 days before an existing subscriber moves to the current plan. */
class PriceChangeNotice extends Notification
{
    public function __construct(
        public string $business,
        public int $oldPence,
        public int $newPence,
        public int $oldLimit,
        public int $newLimit,
        public CarbonInterface $on,
        public ?string $planName = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = $this->on->format('j M Y');
        $mail = (new MailMessage)
            ->subject("Your plan changes on {$date}")
            ->greeting('Hello '.strtok($notifiable->name, ' ').',')
            ->line("We're giving you 30 days' notice of a change to the plan for {$this->business}.");

        if ($this->planName) {
            $mail->line("From {$date} you will be on our {$this->planName} plan.");
        }

        if ($this->oldPence !== $this->newPence) {
            $mail->line('From '.$date.' the price changes from £'.Pricing::pounds($this->oldPence).' to £'.Pricing::pounds($this->newPence).' per month. The new price applies from your first payment after that date.');
        }
        if ($this->oldLimit !== $this->newLimit) {
            $mail->line("From {$date} the plan covers up to {$this->newLimit} employees (currently {$this->oldLimit}).");
        }

        return $mail
            ->line('You do not need to do anything. There is still no contract: you can cancel any time from Settings → Manage billing.')
            ->action('Sign in', route('login'))
            ->line('Questions? Reply to this email or contact '.config('sponsorsafe.support_email').'.');
    }
}
