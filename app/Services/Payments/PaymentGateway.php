<?php

namespace App\Services\Payments;

use App\Models\Deposit;
use Illuminate\Http\Request;

interface PaymentGateway
{
    public function createCheckout(Deposit $deposit): CheckoutSession;

    public function handleWebhook(Request $request): PaymentWebhookResult;

    public function refund(Deposit $deposit): bool;

    /**
     * Polled by the daily reconciliation job to catch a Deposit whose
     * webhook never arrived — see CLAUDE.md.
     */
    public function isCheckoutPaid(Deposit $deposit): bool;
}
