<?php

namespace App\Notifications;

use App\Models\Enquiry;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Tells the SponsorSafe team about a new website enquiry. */
class NewEnquiry extends Notification
{
    public function __construct(public Enquiry $enquiry) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $e = $this->enquiry;

        return (new MailMessage)
            ->subject("New enquiry: {$e->topic} – {$e->name}")
            ->replyTo($e->email, $e->name)
            ->line("**{$e->name}** ({$e->email}".($e->phone ? ", {$e->phone}" : '').') sent a message about **'.$e->topic.'**.')
            ->line($e->message)
            ->line('Reply within one working day, then mark it handled in the super admin area.');
    }
}
