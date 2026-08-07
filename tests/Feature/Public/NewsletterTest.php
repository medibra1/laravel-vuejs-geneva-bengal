<?php

use App\Models\NewsletterSubscriber;

it('subscribes an email', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);

    $response = $this->post('/fr/newsletter', ['email' => 'fan@example.com']);

    $response->assertRedirect();
    $subscriber = NewsletterSubscriber::where('email', 'fan@example.com')->sole();
    expect($subscriber->unsubscribe_token)->not->toBeNull();
    expect($subscriber->isUnsubscribed())->toBeFalse();
});

it('is idempotent for an email that already subscribed', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    NewsletterSubscriber::factory()->create(['email' => 'fan@example.com']);

    $response = $this->post('/fr/newsletter', ['email' => 'fan@example.com']);

    $response->assertRedirect();
    expect(NewsletterSubscriber::where('email', 'fan@example.com')->count())->toBe(1);
});

it('resubscribes an email that had previously unsubscribed', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'fan@example.com']);

    $response = $this->post('/fr/newsletter', ['email' => 'fan@example.com']);

    $response->assertRedirect();
    expect(NewsletterSubscriber::where('email', 'fan@example.com')->sole()->isUnsubscribed())->toBeFalse();
});

it('validates the email field', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);

    $response = $this->post('/fr/newsletter', ['email' => 'not-an-email']);

    $response->assertSessionHasErrors('email');
});

it('unsubscribes a subscriber via their token link', function () {
    refreshApplicationWithLocale('fr');
    $subscriber = NewsletterSubscriber::factory()->create(['unsubscribe_token' => 'valid-token']);

    $response = $this->get('/fr/newsletter/unsubscribe/valid-token');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Public/NewsletterUnsubscribed')->where('found', true));
    expect($subscriber->fresh()->isUnsubscribed())->toBeTrue();
});

it('renders the same confirmation page for an unknown token', function () {
    refreshApplicationWithLocale('fr');

    $response = $this->get('/fr/newsletter/unsubscribe/does-not-exist');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->component('Public/NewsletterUnsubscribed')->where('found', false));
});
