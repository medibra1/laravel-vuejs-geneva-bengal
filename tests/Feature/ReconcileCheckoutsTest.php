<?php

use App\Enums\CatStatus;
use App\Enums\DepositStatus;
use App\Jobs\ReconcileCheckouts;
use App\Models\Cat;
use App\Models\Deposit;
use App\Models\PaymentIntentTracking;
use App\Models\User;
use App\Notifications\DepositConfirmationUndeliveredNotification;
use App\Notifications\DepositConfirmedNotification;
use App\Notifications\DepositPaidNotification;
use App\Notifications\DepositUnavailableNotification;
use App\Notifications\StripeReconciliationIssueNotification;
use App\Services\Payments\DepositPaymentProcessor;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentWebhookResult;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\Doubles\FailingMailChannel;
use Tests\Doubles\FakePaymentGateway;

beforeEach(function () {
    // Every notification this job can send targets active staff — see
    // NotifiesStaff — which looks these roles up even on the tests that
    // never hit any of them.
    Role::findOrCreate('admin');
    Role::findOrCreate('super_admin');
});

function staleTracking(string $paymentIntentId): PaymentIntentTracking
{
    $tracking = PaymentIntentTracking::query()->create(['payment_intent_id' => $paymentIntentId]);
    // created_at has no factory/mutator to backdate through — set directly,
    // past ReconcileCheckouts::GRACE_PERIOD_MINUTES (5).
    $tracking->forceFill(['created_at' => now()->subMinutes(6)])->save();

    return $tracking;
}

// --- Volet 1: stale PaymentIntentTracking rows -----------------------------

it('never touches a tracking row still within its grace period', function () {
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);
    PaymentIntentTracking::query()->create(['payment_intent_id' => 'pi_fresh']);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect(PaymentIntentTracking::query()->where('payment_intent_id', 'pi_fresh')->exists())->toBeTrue();
    expect(Deposit::count())->toBe(0);
});

it('replays a lost webhook and creates the Deposit when Stripe reports the PaymentIntent as paid', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Available->value);
    $tracking = staleTracking('pi_lost_webhook');
    $gateway = new FakePaymentGateway;
    $gateway->checkoutDataResults['pi_lost_webhook'] = new PaymentWebhookResult(
        handled: true,
        paymentIntentId: 'pi_lost_webhook',
        metadata: ['cat_id' => (string) $cat->id, 'name' => 'Marie Dupont', 'email' => 'marie@example.com', 'locale' => 'fr'],
        amount: 50000,
        currency: 'CHF',
    );
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    $deposit = Deposit::sole();
    expect($deposit->status)->toBe(DepositStatus::Paid);
    expect($deposit->provider_reference)->toBe('pi_lost_webhook');
    expect($deposit->cat_id)->toBe($cat->id);
    expect($cat->fresh()->status)->toBe(CatStatus::Pending->value);
    expect(PaymentIntentTracking::query()->whereKey($tracking->id)->exists())->toBeFalse();
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
    Notification::assertSentTo($admin, DepositPaidNotification::class);
});

it('does not duplicate the deposit when a webhook actually did arrive between the row going stale and this job running', function () {
    Notification::fake();
    $cat = Cat::factory()->create();
    $tracking = staleTracking('pi_already_processed');
    Deposit::factory()->paid()->create(['cat_id' => $cat->id, 'provider_reference' => 'pi_already_processed']);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect(Deposit::where('provider_reference', 'pi_already_processed')->count())->toBe(1);
    expect(PaymentIntentTracking::query()->whereKey($tracking->id)->exists())->toBeFalse();
});

it('deletes the tracking row when Stripe reports the PaymentIntent as never paid', function () {
    $tracking = staleTracking('pi_never_paid');
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect(PaymentIntentTracking::query()->whereKey($tracking->id)->exists())->toBeFalse();
    expect(Deposit::count())->toBe(0);
});

it('notifies staff and keeps processing the rest of the batch when Stripe itself errors out', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $firstTracking = staleTracking('pi_error_a');
    $secondTracking = staleTracking('pi_error_b');
    $gateway = new FakePaymentGateway;
    $gateway->retrieveCheckoutDataException = new RuntimeException('Stripe API unreachable');
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    // Untouched — a failed check is not a resolved outcome either way.
    expect(PaymentIntentTracking::query()->whereKey($firstTracking->id)->exists())->toBeTrue();
    expect(PaymentIntentTracking::query()->whereKey($secondTracking->id)->exists())->toBeTrue();
    Notification::assertSentTo(
        $admin,
        StripeReconciliationIssueNotification::class,
        fn (StripeReconciliationIssueNotification $notification) => $notification->errorMessage === 'Stripe API unreachable'
            && $notification->tracking->is($firstTracking),
    );
    Notification::assertSentTo(
        $admin,
        StripeReconciliationIssueNotification::class,
        fn (StripeReconciliationIssueNotification $notification) => $notification->tracking->is($secondTracking),
    );
});

it('cancels a losing card PaymentIntent replayed via reconciliation, creating no deposit for the loser', function () {
    Notification::fake();
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Pending->value);
    Deposit::factory()->paid()->create(['cat_id' => $cat->id, 'provider_reference' => 'pi_winner']);
    staleTracking('pi_loser');
    $gateway = new FakePaymentGateway;
    $gateway->checkoutDataResults['pi_loser'] = new PaymentWebhookResult(
        handled: true,
        paymentIntentId: 'pi_loser',
        metadata: ['cat_id' => (string) $cat->id, 'name' => 'Second Visitor', 'email' => 'second@example.com'],
        amount: 50000,
        currency: 'CHF',
    );
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect(Deposit::where('email', 'second@example.com')->exists())->toBeFalse();
    expect(Deposit::count())->toBe(1);
    expect($gateway->cancelledProviderReferences)->toBe(['pi_loser']);
    Notification::assertSentOnDemand(DepositUnavailableNotification::class);
});

it('clears the tracking row for a losing PaymentIntent and does not retry it on a later run', function () {
    // Deliberately no Notification::fake() here — the whole point of this
    // test is to exercise DepositUnavailableNotification's real send path.
    // Notification::fake() intercepts before any serialization happens,
    // which is exactly what let a real bug slip through: the notification
    // used to implement ShouldQueue while carrying a transient, never-saved
    // Deposit (the losing side of a race is never persisted, see
    // DepositPaymentProcessor::createFromPayment()) — even under
    // QUEUE_CONNECTION=sync, Laravel serializes queued notifications via
    // SerializesModels before "dispatching" them, and re-resolving an
    // unsaved model by its null id threw ModelNotFoundException *after*
    // the real (irreversible) Stripe refund had already gone through,
    // aborting the transaction and leaving the tracking row uncleared. A
    // second reconciliation run then retried the same already-refunded
    // PaymentIntent and failed again on Stripe's side ("cannot refund
    // amount=0"), which — uncaught by each()'s per-row try/catch at the
    // time — aborted the whole batch and starved every other stale row
    // queued behind it. See CLAUDE.md.
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $cat = Cat::factory()->create();
    $cat->setStatus(CatStatus::Pending->value);
    Deposit::factory()->paid()->create(['cat_id' => $cat->id, 'provider_reference' => 'pi_winner']);
    $tracking = staleTracking('pi_loser');
    $gateway = new FakePaymentGateway;
    $gateway->checkoutDataResults['pi_loser'] = new PaymentWebhookResult(
        handled: true,
        paymentIntentId: 'pi_loser',
        metadata: ['cat_id' => (string) $cat->id, 'name' => 'Second Visitor', 'email' => 'second@example.com'],
        amount: 50000,
        currency: 'CHF',
    );
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect(PaymentIntentTracking::query()->whereKey($tracking->id)->exists())->toBeFalse();
    expect($gateway->cancelledProviderReferences)->toBe(['pi_loser']);

    // A second run must never touch 'pi_loser' again — nothing left to
    // track, and the fake gateway's cancelledProviderReferences would grow
    // to two entries if it were retried.
    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($gateway->cancelledProviderReferences)->toBe(['pi_loser']);
});

it('keeps processing the rest of the batch when one row throws — a per-row failure must not abort the whole run', function () {
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $failingCat = Cat::factory()->create();
    $healthyCat = Cat::factory()->create();
    $healthyCat->setStatus(CatStatus::Available->value);
    staleTracking('pi_throws');
    staleTracking('pi_after');
    $gateway = new FakePaymentGateway;
    $gateway->checkoutDataResults['pi_throws'] = new PaymentWebhookResult(handled: true, paymentIntentId: 'pi_throws', metadata: ['cat_id' => (string) $failingCat->id, 'name' => 'A', 'email' => 'a@example.com'], amount: 50000, currency: 'CHF');
    $gateway->checkoutDataResults['pi_after'] = new PaymentWebhookResult(handled: true, paymentIntentId: 'pi_after', metadata: ['cat_id' => (string) $healthyCat->id, 'name' => 'B', 'email' => 'b@example.com'], amount: 50000, currency: 'CHF');
    $gateway->captureException = new RuntimeException('simulated Stripe failure on this row only');
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    // pi_after must still have been reached and resolved into a real
    // deposit, even though pi_throws (processed first, alphabetically/by
    // insertion order) blew up.
    expect(Deposit::where('provider_reference', 'pi_after')->exists())->toBeTrue();
});

// --- Volet 2: failed confirmation emails -----------------------------------

it('retries a paid deposit whose confirmation email never went out, and marks it sent on success', function () {
    Notification::fake();
    $deposit = Deposit::factory()->paid()->create([
        'confirmation_sent_at' => null,
        'confirmation_attempts' => 2,
    ]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->confirmation_sent_at)->not->toBeNull();
    expect($deposit->fresh()->confirmation_attempts)->toBe(3);
    Notification::assertSentOnDemand(DepositConfirmedNotification::class);
});

it('never retries a deposit whose confirmation already went out', function () {
    Notification::fake();
    $deposit = Deposit::factory()->paid()->create([
        'confirmation_sent_at' => now()->subHour(),
        'confirmation_attempts' => 1,
    ]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->confirmation_attempts)->toBe(1);
    Notification::assertNothingSent();
});

it('never retries a deposit that is not paid', function () {
    Notification::fake();
    $deposit = Deposit::factory()->create([
        'status' => DepositStatus::Pending,
        'confirmation_sent_at' => null,
        'confirmation_attempts' => 0,
    ]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->confirmation_attempts)->toBe(0);
    Notification::assertNothingSent();
});

it('stops retrying and notifies staff once a confirmation email has failed 10 times', function () {
    // Not Notification::fake() here: FailingMailChannel must actually run
    // for real (this test's whole point is to make
    // sendClientConfirmation() genuinely fail) — a full fake would
    // intercept the send before it ever reaches the channel and make it
    // trivially "succeed". DepositConfirmationUndeliveredNotification's
    // own send is verified via its real `database` write instead of
    // Notification::assertSentTo().
    $this->app->bind(MailChannel::class, FailingMailChannel::class);
    $admin = User::factory()->create(['is_active' => true]);
    $admin->assignRole('admin');
    $deposit = Deposit::factory()->paid()->create([
        'confirmation_sent_at' => null,
        'confirmation_attempts' => 9,
    ]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->confirmation_attempts)->toBe(10);
    expect($deposit->fresh()->confirmation_sent_at)->toBeNull();
    expect($admin->fresh()->notifications()->where('type', DepositConfirmationUndeliveredNotification::class)->exists())->toBeTrue();
});

it('does not pick up a deposit that already reached the max attempts on a previous run', function () {
    Notification::fake();
    $deposit = Deposit::factory()->paid()->create([
        'confirmation_sent_at' => null,
        'confirmation_attempts' => 10,
    ]);
    $gateway = new FakePaymentGateway;
    $this->app->instance(PaymentGateway::class, $gateway);

    (new ReconcileCheckouts)->handle($gateway, app(DepositPaymentProcessor::class));

    expect($deposit->fresh()->confirmation_attempts)->toBe(10);
    Notification::assertNothingSent();
});
