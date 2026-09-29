<?php

namespace App\Notifications;

use App\Services\PasswordLinks;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PortalInvite extends Notification
{
    public function __construct(public string $token, public string $business) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Set up your {$this->business} employee portal")
            ->greeting('Hello '.strtok($notifiable->name, ' ').',')
            ->line("{$this->business} uses SponsorSafe to keep your employment records up to date.")
            ->line('In the portal you can upload documents your employer asks for, request leave and keep your details current.')
            ->action('Set your password', route('password.set', $this->token))
            ->line('This link works once and expires in '.PasswordLinks::INVITE_DAYS.' days. If it has expired, ask your employer to send a new one.');
    }
}
