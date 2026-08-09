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
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nouvelle demande de contact — Geneva Bengal')
            ->line("Nouvelle demande de {$this->contactRequest->name} ({$this->contactRequest->email}).")
            ->line('Motif : '.$this->contactRequest->reason->value)
            ->line($this->contactRequest->message);
    }

    /**
     * Consumed by NotificationBell.vue via HandleInertiaRequests'
     * shared prop — see CLAUDE.md's notification spec. No `show` route
     * exists for a single contact request, so the link goes to the list.
     *
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'contact_request',
            'title' => 'Nouvelle demande de contact',
            'message' => "{$this->contactRequest->name} — {$this->contactRequest->reason->value}",
            'url' => route('admin.contact-requests.index'),
        ];
    }
}
