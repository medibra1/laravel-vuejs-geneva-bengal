<?php

namespace App\Notifications;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the subscriber themselves (on-demand, via
 * Notification::route('mail', ...) — a NewsletterSubscriber isn't
 * Notifiable, same as Deposit/ContactRequest). Always carries the
 * unsubscribe link so a one-click opt-out is available from the very
 * first email — see CLAUDE.md's newsletter compliance note.
 */
class NewsletterSubscriptionConfirmedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public NewsletterSubscriber $subscriber,
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
            ->subject('Confirmation d\'inscription à la newsletter — Geneva Bengal')
            ->greeting('Bonjour,')
            ->line('Vous êtes désormais inscrit(e) à la newsletter Geneva Bengal.')
            ->line('Vous pouvez vous désinscrire à tout moment en un clic.')
            ->action('Se désabonner', route('newsletter.unsubscribe', $this->subscriber->unsubscribe_token));
    }
}
