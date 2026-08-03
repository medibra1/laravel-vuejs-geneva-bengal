<?php

use App\Models\NewsletterSubscriber;

it('subscribes an email', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);

    $response = $this->post('/fr/newsletter', ['email' => 'fan@example.com']);

    $response->assertRedirect();
    expect(NewsletterSubscriber::where('email', 'fan@example.com')->count())->toBe(1);
});

it('is idempotent for an email that already subscribed', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    NewsletterSubscriber::factory()->create(['email' => 'fan@example.com']);

    $response = $this->post('/fr/newsletter', ['email' => 'fan@example.com']);

    $response->assertRedirect();
    expect(NewsletterSubscriber::where('email', 'fan@example.com')->count())->toBe(1);
});

it('validates the email field', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);

    $response = $this->post('/fr/newsletter', ['email' => 'not-an-email']);

    $response->assertSessionHasErrors('email');
});
