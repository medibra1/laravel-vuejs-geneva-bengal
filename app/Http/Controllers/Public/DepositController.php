<?php

namespace App\Http\Controllers\Public;

use App\Enums\DepositStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreDepositRequest;
use App\Models\Deposit;
use App\Models\SiteSetting;
use App\Services\Payments\PaymentGateway;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DepositController extends Controller
{
    /**
     * Amount is never taken from the request — it's whatever the client
     * has configured in site_settings, so a visitor can't tamper with it.
     *
     * Renders the checkout page directly (rather than redirecting to
     * show()) so the PaymentIntent's client_secret — only meaningful right
     * after creation, and not persisted (see CLAUDE.md) — reaches the
     * frontend in this same response. Public/DepositPay.vue mounts a
     * Stripe.js Payment Element on this same page and confirms the
     * payment client-side; no more cross-origin redirect to a
     * Stripe-hosted page, so no more Inertia::location() workaround.
     *
     * Deliberately does *not* call DepositPaymentProcessor::reserve() —
     * that would hold the cat (`en_attente`) the instant a visitor merely
     * lands on the payment page, before any money has moved. That made
     * sense back when a pending deposit itself blocked a second one (see
     * Deposit::blocksNewReservation()'s old behavior); now that blocking
     * only happens once a deposit is actually `paid`, holding the cat
     * this early no longer protects anything — it would just show the
     * cat as unavailable to every other visitor while this one is still
     * mid-checkout, possibly never finishing. confirmPaid() (called from
     * DepositPaymentProcessor::markPaid()) is what actually reserves the
     * cat, exactly once payment is confirmed. Admin\DepositController's
     * own flows still call reserve() immediately — a staff-recorded
     * reservation is trusted the moment it's entered, unlike a public
     * visitor who has not yet paid anything.
     */
    public function store(StoreDepositRequest $request, PaymentGateway $gateway): Response
    {
        $catId = $request->validated('cat_id');

        // CatIsAvailableForDeposit (the FormRequest rule) already checked
        // this once, but FormRequest validation runs before this method is
        // even entered — another visitor's payment could have been
        // captured in the window since. Re-checked here, immediately
        // before creating anything, so a losing visitor gets an ordinary
        // validation error instead of a PaymentIntent for a cat that's
        // already gone. This is a UX shortcut, not the actual safety net:
        // DepositPaymentProcessor::markPaid()'s own atomic re-check (see
        // CLAUDE.md) is what guarantees the cat is never charged twice,
        // even if this check's own narrow window is lost.
        if ($catId !== null && Deposit::blocksNewReservation((int) $catId)) {
            throw ValidationException::withMessages([
                'cat_id' => __('This kitten has already been reserved.'),
            ]);
        }

        $deposit = Deposit::create([
            'cat_id' => $catId,
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'amount' => SiteSetting::get('deposit_amount', 50000),
            'currency' => 'CHF',
            'status' => DepositStatus::Pending,
            'provider' => 'stripe',
        ]);

        $intent = $gateway->createPaymentIntent($deposit);

        // Persisted immediately (not just once paid) so the reconciliation
        // job has something to poll even if the webhook never arrives.
        $deposit->update(['provider_reference' => $intent->id]);

        return Inertia::render('Public/DepositPay', [
            'depositId' => $deposit->id,
            'clientSecret' => $intent->clientSecret,
            // The publishable key is safe to expose client-side by design
            // (it can only create PaymentIntents/confirm payments, never
            // move money on its own) — read from config rather than
            // hardcoded so it follows STRIPE_KEY per environment.
            'stripePublishableKey' => config('services.stripe.key'),
            'catName' => $deposit->cat?->name,
            'amount' => $deposit->amount,
            'currency' => $deposit->currency,
        ]);
    }

    /**
     * Reached on a full page reload of the checkout/status page (e.g. the
     * client_secret from store() was lost, or the visitor is just checking
     * back later). Only ever shows a status message — the webhook (see
     * Public\StripeWebhookController) is the only source of truth for
     * whether the Deposit actually got paid, never this page's own state.
     * See CLAUDE.md.
     */
    public function show(Deposit $deposit): Response
    {
        return Inertia::render('Public/DepositReturn', [
            'depositStatus' => $deposit->status,
        ]);
    }
}
