<?php

namespace App\Notifications;

use App\Services\PasswordLinks;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Another super admin added from the Super admins page: set a password, then add an authenticator app. */
class SuperAdminInvite extends Notification
{
    public function __construct(public string $token, public string $invitedBy) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("You've been added as a ".config('app.name').' super admin')
            ->greeting('Hello '.strtok($notifiable->name, ' ').',')
            ->line("{$this->invitedBy} has added you as a super admin of ".config('app.name').'. Super admins manage subscribing businesses, pricing, payment gateways and enquiries.')
            ->action('Set your password', route('ops.password.set', $this->token))
            ->line('When you first sign in you will add an authenticator app (such as Google or Microsoft Authenticator). This link works once and expires in '.PasswordLinks::INVITE_DAYS.' days.')
            ->line('The super admin area only opens from approved internet (IP) addresses. If the link shows "Not found", ask for your IP address to be added.');
    }
}
