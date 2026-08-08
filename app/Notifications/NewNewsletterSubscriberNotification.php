<?php

namespace App\Notifications;

use App\Models\NewsletterSubscriber;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Bell-only (no mail) — unlike contact requests and deposits, a newsletter
 * signup is routine and frequent enough that emailing every active admin
 * for each one would just be noise. Staff can still see it land in the
 * list at their own pace.
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
        return ['database'];
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
