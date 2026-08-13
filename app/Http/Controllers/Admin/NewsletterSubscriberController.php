<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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
     * Full CSV of every subscriber, for one-off offline use. Semicolon
     * delimiter (not comma) and a UTF-8 BOM: Excel's French locale reads
     * comma-separated files as a single column and mangles accented
     * headers without the BOM.
     */
    public function export(): StreamedResponse
    {
        $subscribers = NewsletterSubscriber::query()->orderBy('email')->get();

        return response()->streamDownload(function () use ($subscribers): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['E-mail', 'Statut', 'Inscrit le', 'Désabonné le'], ';');

            foreach ($subscribers as $subscriber) {
                fputcsv($handle, [
                    $subscriber->email,
                    $subscriber->isUnsubscribed() ? 'Désabonné' : 'Actif',
                    $subscriber->created_at?->toDateString(),
                    $subscriber->unsubscribed_at?->toDateString(),
                ], ';');
            }

            fclose($handle);
        }, 'newsletter-subscribers.csv', ['Content-Type' => 'text/csv']);
    }

    public function toggleUnsubscribed(NewsletterSubscriber $newsletterSubscriber): RedirectResponse
    {
        $newsletterSubscriber->update([
            'unsubscribed_at' => $newsletterSubscriber->isUnsubscribed() ? null : now(),
        ]);

        return back()->with('success', 'Statut de l\'abonné mis à jour.');
    }
}
