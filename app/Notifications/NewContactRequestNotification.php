<?php

namespace App\Notifications;

use App\Models\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewContactRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public ContactRequest $contactRequest,
    ) {}

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
            ->subject('Nouvelle demande de contact — Geneva Bengal')
            ->line("Nouvelle demande de {$this->contactRequest->name} ({$this->contactRequest->email}).")
            ->line('Motif : '.$this->contactRequest->reason->value)
            ->line($this->contactRequest->message);
    }
}
