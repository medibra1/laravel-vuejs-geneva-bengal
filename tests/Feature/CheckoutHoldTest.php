<?php

use App\Models\Cat;
use App\Models\CheckoutHold;

it('acquires a hold on a cat with no existing hold', function () {
    $cat = Cat::factory()->create();

    $acquired = CheckoutHold::acquire($cat->id, 'pi_123');

    expect($acquired)->toBeTrue();
    expect(CheckoutHold::query()->where('cat_id', $cat->id)->where('payment_intent_id', 'pi_123')->exists())->toBeTrue();
});

it('refuses to acquire a hold while a live hold exists', function () {
    $cat = Cat::factory()->create();
    CheckoutHold::acquire($cat->id, 'pi_first');

    $acquired = CheckoutHold::acquire($cat->id, 'pi_second');

    expect($acquired)->toBeFalse();
    expect(CheckoutHold::query()->where('cat_id', $cat->id)->count())->toBe(1);
    expect(CheckoutHold::query()->where('payment_intent_id', 'pi_first')->exists())->toBeTrue();
});

it('allows acquiring a new hold once the previous one has passed expires_at', function () {
    $cat = Cat::factory()->create();
    CheckoutHold::query()->create([
        'cat_id' => $cat->id,
        'payment_intent_id' => 'pi_expired',
        'expires_at' => now()->subMinute(),
        'hard_expires_at' => now()->addMinutes(10),
    ]);

    $acquired = CheckoutHold::acquire($cat->id, 'pi_new');

    expect($acquired)->toBeTrue();
    expect(CheckoutHold::query()->where('cat_id', $cat->id)->where('payment_intent_id', 'pi_new')->exists())->toBeTrue();
    expect(CheckoutHold::query()->where('payment_intent_id', 'pi_expired')->exists())->toBeFalse();
});

it('allows acquiring a new hold once the previous one has passed hard_expires_at', function () {
    $cat = Cat::factory()->create();
    CheckoutHold::query()->create([
        'cat_id' => $cat->id,
        'payment_intent_id' => 'pi_hard_expired',
        // expires_at still in the future (kept alive by extend() pings),
        // but hard_expires_at has been crossed regardless.
        'expires_at' => now()->addMinute(),
        'hard_expires_at' => now()->subMinute(),
    ]);

    $acquired = CheckoutHold::acquire($cat->id, 'pi_new');

    expect($acquired)->toBeTrue();
    expect(CheckoutHold::query()->where('payment_intent_id', 'pi_hard_expired')->exists())->toBeFalse();
});

it('extend prolongs expires_at without touching hard_expires_at', function () {
    $cat = Cat::factory()->create();
    $hardExpiresAt = now()->addMinutes(10);
    CheckoutHold::query()->create([
        'cat_id' => $cat->id,
        'payment_intent_id' => 'pi_123',
        'expires_at' => now()->addSeconds(30),
        'hard_expires_at' => $hardExpiresAt,
    ]);

    $extended = CheckoutHold::extend('pi_123');

    expect($extended)->toBeTrue();
    $hold = CheckoutHold::query()->where('payment_intent_id', 'pi_123')->firstOrFail();
    expect(now()->diffInSeconds($hold->expires_at))->toBeGreaterThan(CheckoutHold::TTL_SECONDS - 5);
    expect($hold->hard_expires_at->equalTo($hardExpiresAt->startOfSecond()))->toBeTrue();
});

it('extend refuses once hard_expires_at has passed even if expires_at was just extended', function () {
    $cat = Cat::factory()->create();
    CheckoutHold::query()->create([
        'cat_id' => $cat->id,
        'payment_intent_id' => 'pi_123',
        'expires_at' => now()->addMinute(),
        'hard_expires_at' => now()->subSecond(),
    ]);

    $extended = CheckoutHold::extend('pi_123');

    expect($extended)->toBeFalse();
});

it('extend refuses once expires_at has already passed', function () {
    $cat = Cat::factory()->create();
    CheckoutHold::query()->create([
        'cat_id' => $cat->id,
        'payment_intent_id' => 'pi_123',
        'expires_at' => now()->subSecond(),
        'hard_expires_at' => now()->addMinutes(10),
    ]);

    $extended = CheckoutHold::extend('pi_123');

    expect($extended)->toBeFalse();
});

it('extend returns false for an unknown payment_intent_id', function () {
    $extended = CheckoutHold::extend('pi_does_not_exist');

    expect($extended)->toBeFalse();
});

it('release deletes an existing hold', function () {
    $cat = Cat::factory()->create();
    CheckoutHold::acquire($cat->id, 'pi_123');

    CheckoutHold::release('pi_123');

    expect(CheckoutHold::query()->where('payment_intent_id', 'pi_123')->exists())->toBeFalse();
});

it('release is idempotent when the hold does not exist', function () {
    CheckoutHold::release('pi_does_not_exist');

    expect(CheckoutHold::query()->count())->toBe(0);
});
