<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class StaffInvitation extends ResetPassword
{
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject(__('invitation.subject'))
            ->line(__('invitation.intro'))
            ->action(__('invitation.action'), rtrim(config('app.url'), '/').route('password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false))
            ->line(__('invitation.expiry', ['minutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire')]))
            ->line(__('invitation.verify'))
            ->line(__('invitation.ignore'));
    }
}
