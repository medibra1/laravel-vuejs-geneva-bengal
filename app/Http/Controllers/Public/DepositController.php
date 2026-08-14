<?php

namespace App\Http\Controllers\Public;

use App\Enums\DepositStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreDepositRequest;
use App\Models\Cat;
use App\Models\CheckoutHold;
use App\Models\Deposit;
use App\Models\SiteSetting;
use App\Services\Payments\CheckoutData;
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
     * No Deposit row is created here at all (see CLAUDE.md) — the webhook
     * is what builds it, once payment is actually confirmed, from the
     * checkout data carried in the PaymentIntent's own metadata (see
     * StripeGateway::createPaymentIntent()). A visitor who abandons the
     * checkout page therefore never leaves a `deposits` row behind.
     *
     * The cat itself is never held here either, for the same reason it
     * wasn't when this method still created a pending Deposit: only a
     * confirmed payment should make a cat unavailable to everyone else.
     * What *does* need protecting at this point is the payment slot
     * itself — CheckoutHold::acquire() stops a second visitor from
     * starting a parallel payment for the same cat while this one is in
     * flight (see CLAUDE.md and CheckoutHold's own docblock). A waiting-
     * list checkout (no cat_id) has no such single resource to protect,
     * so no hold is acquired for it — any number of visitors can join the
     * waiting list in parallel.
     */
    public function store(StoreDepositRequest $request, PaymentGateway $gateway): Response
    {
        $catId = $request->validated('cat_id');
        $catId = $catId === null ? null : (int) $catId;

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
        if ($catId !== null && Deposit::blocksNewReservation($catId)) {
            throw ValidationException::withMessages([
                'cat_id' => __('This kitten has already been reserved.'),
            ]);
        }

        $checkoutData = new CheckoutData(
            catId: $catId,
            name: $request->validated('name'),
            email: $request->validated('email'),
            phone: $request->validated('phone'),
            // Captured now — the only point where the visitor's active
            // locale is known. The webhook that eventually builds the
            // Deposit from this PaymentIntent's metadata is not running in
            // the context of any particular visitor's request. See
            // CLAUDE.md.
            locale: app()->getLocale(),
            amount: SiteSetting::get('deposit_amount', 50000),
            currency: 'CHF',
        );

        $intent = $gateway->createPaymentIntent($checkoutData);

        // Needs the PaymentIntent's own id, so it can only happen after
        // creation above. If the hold can't be acquired, the PaymentIntent
        // that was just created is worthless — cancelled immediately
        // rather than left dangling; it was never confirmed, so nothing
        // has been charged.
        if ($catId !== null && ! CheckoutHold::acquire($catId, $intent->id)) {
            $gateway->cancelAuthorization(new Deposit(['provider_reference' => $intent->id]));

            // Distinct from "already reserved" above: the cat itself is
            // still available, another visitor is simply mid-payment for
            // it right now. Conflating the two messages would tell this
            // visitor the cat is gone when it might free up in minutes.
            throw ValidationException::withMessages([
                'cat_id' => __('Someone else is currently paying for this kitten. Please try again in a few minutes.'),
            ]);
        }

        return Inertia::render('Public/DepositPay', [
            'paymentIntentId' => $intent->id,
            'clientSecret' => $intent->clientSecret,
            // The publishable key is safe to expose client-side by design
            // (it can only create PaymentIntents/confirm payments, never
            // move money on its own) — read from config rather than
            // hardcoded so it follows STRIPE_KEY per environment.
            'stripePublishableKey' => config('services.stripe.key'),
            'catName' => $catId === null ? null : Cat::find($catId)?->name,
            'amount' => $checkoutData->amount,
            'currency' => $checkoutData->currency,
        ]);
    }

    /**
     * Reached on a full page reload of the checkout/status page (e.g. the
     * client_secret from store() was lost, or the visitor is just checking
     * back later). Only ever shows a status message — the webhook (see
     * Public\StripeWebhookController) is the only source of truth for
     * whether the Deposit actually got paid, never this page's own state.
     * See CLAUDE.md.
     *
     * Keyed on the PaymentIntent id rather than a Deposit id: store() no
     * longer creates a Deposit up front (see CLAUDE.md), so the webhook
     * may not have built one yet by the time the visitor lands here —
     * reported as "pending" in that case, same as any other in-flight
     * checkout, rather than a 404.
     */
    public function show(string $paymentIntentId): Response
    {
        $deposit = Deposit::where('provider_reference', $paymentIntentId)->first();

        return Inertia::render('Public/DepositReturn', [
            'depositStatus' => $deposit === null ? DepositStatus::Pending : $deposit->status,
        ]);
    }
}
