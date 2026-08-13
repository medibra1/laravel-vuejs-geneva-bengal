<?php

namespace App\Notifications;

use App\Models\ContactRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the sender themselves (on-demand, via
 * Notification::route('mail', ...) — a ContactRequest isn't Notifiable,
 * same as Deposit/NewsletterSubscriber). Plain acknowledgement that the
 * message was received, no reply content — the actual reply happens by a
 * staff member emailing the sender directly, outside this app.
 */
class ContactRequestConfirmedNotification extends Notification implements ShouldQueue
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
            ->subject('Votre message a bien été reçu — Geneva Bengal')
            ->greeting("Bonjour {$this->contactRequest->name},")
            ->line('Nous avons bien reçu votre message et nous vous répondrons dans les plus brefs délais.')
            ->line('Voici un rappel de votre message :')
            ->line($this->contactRequest->message);
    }
}
