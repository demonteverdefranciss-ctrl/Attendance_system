<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $code)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Bigaa ES Attendance System password reset code')
            ->greeting("Hello {$notifiable->name},")
            ->line('We received a request to reset the password for your Attendance System account.')
            ->line('Enter this verification code in the app to continue:')
            ->line("**{$this->code}**")
            ->line('This code is valid for 10 minutes and can only be used once.')
            ->line('If you did not request this change, you can safely ignore this message. Your password will remain unchanged.')
            ->salutation('Regards, Bigaa ES (Capstone Project)');
    }
}