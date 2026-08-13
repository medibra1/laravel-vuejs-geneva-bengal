<?php

use App\Enums\ContactReason;
use App\Models\ContactRequest;
use App\Models\User;
use App\Notifications\ContactRequestConfirmedNotification;
use App\Notifications\NewContactRequestNotification;
use Illuminate\Support\Facades\Notification;
use Spatie\Honeypot\Honeypot;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

it('creates a contact request and notifies active admin staff', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    Notification::fake();

    $activeAdmin = User::factory()->create(['is_active' => true]);
    $activeAdmin->assignRole('admin');
    $inactiveAdmin = User::factory()->create(['is_active' => false]);
    $inactiveAdmin->assignRole('admin');
    $regularUser = User::factory()->create(['is_active' => true]);

    $response = $this->post('/fr/contact', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'reason' => ContactReason::Adopt->value,
        'message' => 'Je souhaite adopter un chaton.',
    ]);

    $response->assertRedirect();
    expect(ContactRequest::count())->toBe(1);
    Notification::assertSentTo($activeAdmin, NewContactRequestNotification::class);
    Notification::assertNotSentTo($inactiveAdmin, NewContactRequestNotification::class);
    Notification::assertNotSentTo($regularUser, NewContactRequestNotification::class);
});

it('emails the sender an acknowledgement of receipt', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);
    Notification::fake();

    $this->post('/fr/contact', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'reason' => ContactReason::Adopt->value,
        'message' => 'Je souhaite adopter un chaton.',
    ]);

    Notification::assertSentOnDemand(
        ContactRequestConfirmedNotification::class,
        fn (ContactRequestConfirmedNotification $notification, array $channels, object $notifiable) => $notifiable->routes['mail'] === 'marie@example.com',
    );
});

it('stores the contact notification in the database channel, for the bell', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);

    $activeAdmin = User::factory()->create(['is_active' => true]);
    $activeAdmin->assignRole('admin');

    $this->post('/fr/contact', [
        'name' => 'Marie Dupont',
        'email' => 'marie@example.com',
        'reason' => ContactReason::Adopt->value,
        'message' => 'Je souhaite adopter un chaton.',
    ]);

    expect($activeAdmin->notifications()->count())->toBe(1);
    expect($activeAdmin->unreadNotifications()->count())->toBe(1);
});

it('validates required fields', function () {
    refreshApplicationWithLocale('fr');
    config(['honeypot.enabled' => false]);

    $response = $this->post('/fr/contact', []);

    $response->assertSessionHasErrors(['name', 'email', 'reason', 'message']);
});

it('silently discards spam submissions caught by the honeypot', function () {
    refreshApplicationWithLocale('fr');

    $honeypot = app(Honeypot::class);

    $response = $this->post('/fr/contact', [
        'name' => 'Bot',
        'email' => 'bot@example.com',
        'reason' => ContactReason::Question->value,
        'message' => 'spam',
        $honeypot->nameFieldName() => 'i-am-a-bot',
        $honeypot->validFromFieldName() => $honeypot->encryptedValidFrom(),
    ]);

    $response->assertOk();
    expect(ContactRequest::count())->toBe(0);
});
