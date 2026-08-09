<?php

use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Notifications\NewNewsletterSubscriberNotification;
use App\Notifications\NewsletterSubscriptionConfirmedNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // store() notifies active staff on a new/re- subscription — see
    // NotifiesStaff — which looks these roles up even on the idempotent
    // test below that never reaches that branch.
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('subscribes an email, notifies staff, and emails a confirmation with the unsubscribe link', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    Notification::fake();
    $activeAdmin = User::factory()->create(['is_active' => true]);
    $activeAdmin->assignRole('admin');

    $response = $this->post('/fr/newsletter', ['email' => 'fan@example.com']);

    $response->assertRedirect();
    $subscriber = NewsletterSubscriber::where('email', 'fan@example.com')->sole();
    expect($subscriber->unsubscribe_token)->not->toBeNull();
    expect($subscriber->isUnsubscribed())->toBeFalse();
    Notification::assertSentTo($activeAdmin, NewNewsletterSubscriberNotification::class);
    Notification::assertSentOnDemand(
        NewsletterSubscriptionConfirmedNotification::class,
        fn (NewsletterSubscriptionConfirmedNotification $notification, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'fan@example.com',
    );
});

it('is idempotent for an email that already subscribed, and does not notify or email again', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    Notification::fake();
    NewsletterSubscriber::factory()->create(['email' => 'fan@example.com']);

    $response = $this->post('/fr/newsletter', ['email' => 'fan@example.com']);

    $response->assertRedirect();
    expect(NewsletterSubscriber::where('email', 'fan@example.com')->count())->toBe(1);
    Notification::assertNothingSent();
});

it('resubscribes an email that had previously unsubscribed, and notifies/emails again', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    Notification::fake();
    $activeAdmin = User::factory()->create(['is_active' => true]);
    $activeAdmin->assignRole('admin');
    NewsletterSubscriber::factory()->unsubscribed()->create(['email' => 'fan@example.com']);

    $response = $this->post('/fr/newsletter', ['email' => 'fan@example.com']);

    $response->assertRedirect();
    expect(NewsletterSubscriber::where('email', 'fan@example.com')->sole()->isUnsubscribed())->toBeFalse();
    Notification::assertSentTo($activeAdmin, NewNewsletterSubscriberNotification::class);
    Notification::assertSentOnDemand(NewsletterSubscriptionConfirmedNotification::class);
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
