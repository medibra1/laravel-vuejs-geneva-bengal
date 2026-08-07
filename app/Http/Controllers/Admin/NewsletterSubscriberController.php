<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SyncNewsletterSubscriberWithBrevo;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class NewsletterSubscriberController extends Controller
{
    public function index(): Response
    {
        $subscribers = NewsletterSubscriber::query()
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/NewsletterSubscribers/Index', [
            'subscribers' => $subscribers,
        ]);
    }

    /**
     * Full CSV of every subscriber — for one-off offline use. Day-to-day
     * campaigns go through the Brevo list this app keeps in sync instead
     * (see BrevoNewsletterService), not through this export.
     */
    public function export(): StreamedResponse
    {
        $subscribers = NewsletterSubscriber::query()->orderBy('email')->get();

        return response()->streamDownload(function () use ($subscribers): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['email', 'status', 'subscribed_at', 'unsubscribed_at']);

            foreach ($subscribers as $subscriber) {
                fputcsv($handle, [
                    $subscriber->email,
                    $subscriber->isUnsubscribed() ? 'unsubscribed' : 'active',
                    $subscriber->created_at?->toDateString(),
                    $subscriber->unsubscribed_at?->toDateString(),
                ]);
            }

            fclose($handle);
        }, 'newsletter-subscribers.csv', ['Content-Type' => 'text/csv']);
    }

    public function toggleUnsubscribed(NewsletterSubscriber $newsletterSubscriber): RedirectResponse
    {
        $newsletterSubscriber->update([
            'unsubscribed_at' => $newsletterSubscriber->isUnsubscribed() ? null : now(),
        ]);

        SyncNewsletterSubscriberWithBrevo::dispatch($newsletterSubscriber);

        return back()->with('success', __('Subscriber status updated.'));
    }
}
