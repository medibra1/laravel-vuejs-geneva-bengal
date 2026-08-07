<?php

namespace App\Http\Controllers\Public;

use App\Enums\DepositStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreDepositRequest;
use App\Models\Deposit;
use App\Models\SiteSetting;
use App\Services\Payments\DepositPaymentProcessor;
use App\Services\Payments\PaymentGateway;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class DepositController extends Controller
{
    /**
     * Amount is never taken from the request — it's whatever the client
     * has configured in site_settings, so a visitor can't tamper with it.
     */
    public function store(StoreDepositRequest $request, PaymentGateway $gateway, DepositPaymentProcessor $processor): SymfonyResponse
    {
        $deposit = Deposit::create([
            'cat_id' => $request->validated('cat_id'),
            'name' => $request->validated('name'),
            'email' => $request->validated('email'),
            'phone' => $request->validated('phone'),
            'amount' => SiteSetting::get('deposit_amount', 50000),
            'currency' => 'CHF',
            'status' => DepositStatus::Pending,
            'provider' => 'stripe',
        ]);

        $processor->reserve($deposit);

        $checkout = $gateway->createCheckout($deposit);

        // Persisted immediately (not just once paid) so the reconciliation
        // job has something to poll even if the webhook never arrives.
        $deposit->update(['provider_reference' => $checkout->id]);

        // Stripe Checkout is a different origin — a normal Inertia redirect
        // would try to follow it as an XHR and fail. Inertia::location()
        // forces a full-page browser visit instead.
        return Inertia::location($checkout->url);
    }

    /**
     * Stripe's success_url / cancel_url land here. This only ever shows a
     * waiting/status message — the webhook (see
     * Public\StripeWebhookController) is the only source of truth for
     * whether the Deposit actually got paid. See CLAUDE.md.
     */
    public function show(Deposit $deposit): Response
    {
        return Inertia::render('Public/DepositReturn', [
            'depositStatus' => $deposit->status,
        ]);
    }
}
