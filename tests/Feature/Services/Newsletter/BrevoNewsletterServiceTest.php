<?php

use App\Models\NewsletterSubscriber;
use App\Services\Newsletter\BrevoNewsletterService;
use Illuminate\Support\Facades\Http;

it('adds an active subscriber to the configured Brevo list', function () {
    Http::fake(['api.brevo.com/*' => Http::response([], 201)]);
    $subscriber = NewsletterSubscriber::factory()->create(['email' => 'fan@example.com']);

    (new BrevoNewsletterService('fake-key', 5))->sync($subscriber);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.brevo.com/v3/contacts'
        && $request->method() === 'POST'
        && $request['email'] === 'fan@example.com'
        && $request['listIds'] === [5]);
});

it('removes an unsubscribed subscriber from the configured Brevo list', function () {
    Http::fake(['api.brevo.com/*' => Http::response([], 204)]);
    $subscriber = NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'fan@example.com']);

    (new BrevoNewsletterService('fake-key', 5))->sync($subscriber);

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && str_contains($request->url(), 'contacts/fan%40example.com')
        && $request['unlinkListIds'] === [5]);
});

it('does nothing when Brevo is not configured', function () {
    Http::fake();
    $subscriber = NewsletterSubscriber::factory()->create();

    (new BrevoNewsletterService(null, null))->sync($subscriber);

    Http::assertNothingSent();
});
