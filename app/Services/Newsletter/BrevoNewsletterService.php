<?php

namespace App\Services\Newsletter;

use App\Models\NewsletterSubscriber;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Keeps a NewsletterSubscriber's membership in a single Brevo contact list
 * in sync — campaign composition/sending itself happens in Brevo's own
 * dashboard, not in this app (see CLAUDE.md).
 */
class BrevoNewsletterService
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly ?int $listId,
    ) {}

    /**
     * No-ops when Brevo isn't configured (e.g. local dev without an API
     * key) rather than throwing, so the public subscribe/unsubscribe flow
     * never depends on a third-party credential being present.
     */
    public function sync(NewsletterSubscriber $subscriber): void
    {
        if (! $this->apiKey || ! $this->listId) {
            return;
        }

        $response = $subscriber->isUnsubscribed()
            ? $this->removeFromList($subscriber)
            : $this->addToList($subscriber);

        if ($response->failed()) {
            Log::warning('Brevo newsletter sync failed', [
                'subscriber_id' => $subscriber->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
        }
    }

    private function addToList(NewsletterSubscriber $subscriber): Response
    {
        return $this->client()->post('contacts', [
            'email' => $subscriber->email,
            'listIds' => [$this->listId],
            'updateEnabled' => true,
        ]);
    }

    private function removeFromList(NewsletterSubscriber $subscriber): Response
    {
        return $this->client()->put('contacts/'.urlencode($subscriber->email), [
            'unlinkListIds' => [$this->listId],
        ]);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl('https://api.brevo.com/v3')
            ->withHeaders(['api-key' => $this->apiKey, 'Accept' => 'application/json']);
    }
}
