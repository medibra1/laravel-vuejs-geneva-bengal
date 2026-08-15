<?php

namespace App\Http\Controllers\Public;

use App\Enums\DepositStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ConfirmPaymentIntentRequest;
use App\Http\Requests\Public\StoreDepositRequest;
use App\Models\Cat;
use App\Models\Deposit;
use App\Models\PaymentIntentTracking;
use App\Models\SiteSetting;
use App\Services\Payments\CheckoutData;
use App\Services\Payments\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DepositController extends Controller
{
    /**
     * Just renders the checkout page — no PaymentIntent, no Stripe call at
     * all at this point (see CLAUDE.md: several visitors can land here and
     * fill in their card in parallel without disturbing each other). The
     * PaymentIntent is only created once the visitor actually clicks "Pay",
     * via confirmIntent() below — that's the moment a real commitment is
     * made, not merely arriving on the form.
     *
     * The availability check here is informative only, to avoid sending
     * someone to a form that's already doomed — not a guarantee. The real
     * guarantee is confirmIntent()'s own re-check, immediately before the
     * PaymentIntent is created.
     */
    public function store(StoreDepositRequest $request): Response
    {
        $catId = $request->validated('cat_id');
        $catId = $catId === null ? null : (int) $catId;
        $cat = $catId === null ? null : Cat::find($catId);

        return Inertia::render('Public/DepositPay', [
            'catId' => $catId,
            'catName' => $cat?->name,
            'catSlug' => $cat?->slug,
            'amount' => SiteSetting::get('deposit_amount', 50000),
            'currency' => 'CHF',
            // The publishable key is safe to expose client-side by design
            // (it can only create PaymentIntents/confirm payments, never
            // move money on its own) — read from config rather than
            // hardcoded so it follows STRIPE_KEY per environment.
            'stripePublishableKey' => config('services.stripe.key'),
            // Carried over from the checkout form so the visitor doesn't
            // have to retype anything — see DepositForm.vue.
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
        ]);
    }

    /**
     * Reached at the "Pay" click, not on page load (see store() above) —
     * this is where a PaymentIntent is actually created, and the only point
     * where Deposit::blocksNewReservation() genuinely matters: several
     * visitors may have reached this same checkout page, but only the first
     * one to get here (and, ultimately, to have their payment win the race
     * in DepositPaymentProcessor::markPaid()/createFromPayment(), see
     * CLAUDE.md) ends up with a real reservation.
     *
     * No lock is taken here — several visitors can each get their own
     * PaymentIntent for the same cat at once, that's assumed (see
     * CLAUDE.md). The real arbitration happens later, when a payment is
     * actually confirmed.
     *
     * The tracking row is written immediately after the PaymentIntent is
     * successfully created, before the client_secret is returned to the
     * browser — see PaymentIntentTracking's own docblock for why: it's the
     * only way ReconcileCheckouts can ever find this PaymentIntent again if
     * the response to the browser is lost (crash, dropped connection) and
     * the webhook is somehow missed too.
     */
    public function confirmIntent(ConfirmPaymentIntentRequest $request, PaymentGateway $gateway): JsonResponse
    {
        $catId = $request->validated('cat_id');
        $catId = $catId === null ? null : (int) $catId;

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

        PaymentIntentTracking::query()->create(['payment_intent_id' => $intent->id]);

        return response()->json([
            'paymentIntentId' => $intent->id,
            'clientSecret' => $intent->clientSecret,
        ]);
    }

    /**
     * Reached on a full page reload of the checkout/status page (e.g. the
     * client_secret from confirmIntent() was lost, or the visitor is just
     * checking back later). Only ever shows a status message — the webhook
     * (see Public\StripeWebhookController) is the only source of truth for
     * whether the Deposit actually got paid, never this page's own state.
     * See CLAUDE.md.
     *
     * Keyed on the PaymentIntent id rather than a Deposit id: confirmIntent()
     * doesn't create a Deposit itself — the webhook may not have built one
     * yet by the time the visitor lands here — reported as "pending" in
     * that case, same as any other in-flight checkout, rather than a 404.
     */
    public function show(string $paymentIntentId): Response
    {
        $deposit = Deposit::where('provider_reference', $paymentIntentId)->first();

        return Inertia::render('Public/DepositReturn', [
            'depositStatus' => $deposit === null ? DepositStatus::Pending : $deposit->status,
            // Only meaningful once paid — the success screen names the
            // address the confirmation was just sent to. Null while still
            // pending (no Deposit exists yet — see CLAUDE.md), harmless
            // since the template only reads it on the isPaid branch.
            'email' => $deposit?->email,
        ]);
    }
}
