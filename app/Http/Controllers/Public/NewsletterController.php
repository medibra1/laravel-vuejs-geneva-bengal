<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreNewsletterSubscriberRequest;
use App\Models\NewsletterSubscriber;
use App\Notifications\Concerns\NotifiesStaff;
use App\Notifications\NewNewsletterSubscriberNotification;
use App\Notifications\NewsletterSubscriptionConfirmedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class NewsletterController extends Controller
{
    use NotifiesStaff;

    public function store(StoreNewsletterSubscriberRequest $request): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::firstOrNew(['email' => $request->validated('email')]);
        $isNew = ! $subscriber->exists;
        $isResubscribing = $subscriber->exists && $subscriber->isUnsubscribed();

        if ($isNew || $isResubscribing) {
            $subscriber->unsubscribe_token ??= Str::random(48);
            $subscriber->unsubscribed_at = null;
            $subscriber->save();

            Notification::send($this->activeStaff(), new NewNewsletterSubscriberNotification($subscriber));
            Notification::route('mail', $subscriber->email)
                ->notify(new NewsletterSubscriptionConfirmedNotification($subscriber));
        }

        return back()->with('success', __('Subscribed.'));
    }

    /**
     * Always renders the same confirmation page, whether the token matched
     * or not, rather than a 404 (an expired/mistyped link shouldn't look
     * broken to the visitor).
     */
    public function unsubscribe(string $token): Response
    {
        $subscriber = NewsletterSubscriber::where('unsubscribe_token', $token)->first();

        if ($subscriber && ! $subscriber->isUnsubscribed()) {
            $subscriber->update(['unsubscribed_at' => now()]);
        }

        return Inertia::render('Public/NewsletterUnsubscribed', [
            'found' => $subscriber !== null,
        ]);
    }
}
