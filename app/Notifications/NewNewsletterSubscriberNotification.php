<?php

namespace App\Notifications;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail + database — the client asked to be emailed on every newsletter
 * signup too, not just see it land in the admin bell. Originally bell-only
 * on the reasoning that this is too frequent/low-stakes to justify an
 * email each time; reversed on explicit request (2026-08-13).
 */
class NewNewsletterSubscriberNotification extends Notification implements ShouldQueue
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
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Nouvel abonné newsletter — Geneva Bengal')
            ->line("Nouvel abonné à la newsletter : {$this->subscriber->email}.");
    }

    /**
     * Consumed by NotificationBell.vue via HandleInertiaRequests' shared
     * prop — see CLAUDE.md's notification spec.
     *
     * @return array<string, string>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'newsletter_subscriber',
            'title' => 'Nouvel abonné newsletter',
            'message' => $this->subscriber->email,
            'url' => route('admin.newsletter-subscribers.index'),
        ];
    }
}
