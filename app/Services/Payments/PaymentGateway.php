<?php

namespace App\Services\Payments;

use App\Models\Deposit;
use Illuminate\Http\Request;

interface PaymentGateway
{
    /**
     * Takes checkout form data rather than a Deposit — the public checkout
     * flow (Public\DepositController::store()) no longer creates one up
     * front (see CLAUDE.md). $checkoutData rides along as PaymentIntent
     * metadata so the webhook can build the real Deposit once payment is
     * confirmed.
     */
    public function createPaymentIntent(CheckoutData $checkoutData): PaymentIntentResult;

    public function handleWebhook(Request $request): PaymentWebhookResult;

    /**
     * Actually debits the client for a PaymentIntent authorized under
     * capture_method: manual — only ever called from
     * DepositPaymentProcessor::markPaid(), after the atomic re-check that
     * no other deposit already won the same cat. See CLAUDE.md.
     */
    public function capture(Deposit $deposit): bool;

    /**
     * Releases a card authorization without ever charging it — the "lost
     * the race" branch of DepositPaymentProcessor::markPaid(), for a
     * PaymentIntent isCaptured() reports as not yet captured. See
     * CLAUDE.md — TWINT can't be released this way, it must be refunded
     * instead (see refund()), since it has no uncaptured-authorization
     * state to release.
     */
    public function cancelAuthorization(Deposit $deposit): bool;

    public function refund(Deposit $deposit): bool;

    /**
     * Polled by the daily reconciliation job to catch a Deposit whose
     * webhook never arrived — see CLAUDE.md.
     */
    public function isCheckoutPaid(Deposit $deposit): bool;

    /**
     * True once the PaymentIntent has actually been charged — checked by
     * DepositPaymentProcessor before deciding capture() vs. no-op, and
     * cancelAuthorization() vs. refund(). Needed because TWINT doesn't
     * support capture_method: manual (see CLAUDE.md): it auto-captures the
     * instant the client confirms in the TWINT app, so by the time our
     * webhook arrives it may already be captured — unlike a card payment,
     * which is always still just authorized at that point.
     */
    public function isCaptured(Deposit $deposit): bool;
}
