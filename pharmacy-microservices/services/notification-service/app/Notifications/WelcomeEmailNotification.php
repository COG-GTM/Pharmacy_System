<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeEmailNotification extends Notification
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->greeting('Welcome to Pharmacy System!')
            ->line('Thank you for registering with us.')
            ->line('You can now browse pharmacies and place orders.')
            ->action('Get Started', url('/'));
    }
}
