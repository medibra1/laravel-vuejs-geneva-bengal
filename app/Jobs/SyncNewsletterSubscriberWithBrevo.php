<?php

namespace App\Jobs;

use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\BrevoNewsletterService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Dispatched off the request cycle whenever a subscriber's list membership
 * changes (subscribe, resubscribe, unsubscribe — public or admin-driven),
 * so a slow/unavailable Brevo API never delays that response.
 */
class SyncNewsletterSubscriberWithBrevo implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly NewsletterSubscriber $subscriber) {}

    public function handle(BrevoNewsletterService $brevo): void
    {
        $brevo->sync($this->subscriber);
    }
}
