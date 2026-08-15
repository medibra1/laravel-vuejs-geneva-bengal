<?php

use App\Models\PaymentIntentTracking;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentWebhookResult;
use Tests\Doubles\FakePaymentGateway;

it('rejects a request without a token', function () {
    config(['app.cron_secret' => 'the-real-secret']);

    $this->get('/cron/run')->assertForbidden();
});

it('rejects a request with the wrong token', function () {
    config(['app.cron_secret' => 'the-real-secret']);

    $this->get('/cron/run?token=wrong')->assertForbidden();
});

it('rejects every request when no secret is configured', function () {
    config(['app.cron_secret' => null]);

    $this->get('/cron/run?token=anything')->assertForbidden();
});

it('runs the scheduler and the queue worker and responds OK given the correct token', function () {
    config(['app.cron_secret' => 'the-real-secret']);

    $this->get('/cron/run?token=the-real-secret')
        ->assertOk()
        ->assertSee('OK');
});

it('reconciles a stale PaymentIntentTracking row regardless of whether schedule:run\'s own 15-minute slot is due', function () {
    // Schedule::job(new ReconcileCheckouts)->everyFifteenMinutes() (see
    // routes/console.php) only actually runs on fixed :00/:15/:30/:45
    // clock slots — schedule:run is a silent no-op every other minute,
    // even though this endpoint still answers "OK" either way (see
    // CLAUDE.md). Dispatching the job directly here, in addition to
    // schedule:run, is what makes every call to this endpoint actually
    // reconcile rather than only the ones that happen to land on a slot.
    config(['app.cron_secret' => 'the-real-secret']);
    $tracking = PaymentIntentTracking::query()->create(['payment_intent_id' => 'pi_stale']);
    $tracking->forceFill(['created_at' => now()->subHour()])->save();
    $gateway = new FakePaymentGateway;
    $gateway->checkoutDataResults['pi_stale'] = new PaymentWebhookResult(handled: false);
    $this->app->instance(PaymentGateway::class, $gateway);

    $this->get('/cron/run?token=the-real-secret')->assertOk();

    // Never paid (handled: false) — resolved as an abandoned checkout,
    // proving the job actually ran rather than schedule:run silently
    // no-op'ing outside its own slot.
    expect(PaymentIntentTracking::query()->whereKey($tracking->id)->exists())->toBeFalse();
});
