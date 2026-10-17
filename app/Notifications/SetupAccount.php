<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class SetupAccount extends Notification
{
    use Queueable;

    /**
     * @return array<int, string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Account Setup'))
            ->line(__('An account has been created for you on :app.', ['app' => config('app.name')]))
            ->action(__('Setup Account'), URL::signedRoute('setup-account', $notifiable))
            ->line(__('You will not receive further notifications until you complete setup.'));
    }
}
