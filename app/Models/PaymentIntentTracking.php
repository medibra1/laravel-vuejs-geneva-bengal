<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A breadcrumb, not a reservation — unlike the CheckoutHold it replaces,
 * this never blocks anything. Written by
 * Public\DepositController::confirmIntent() right after Stripe confirms the
 * PaymentIntent was created, before the client_secret is even returned to
 * the browser (see CLAUDE.md), so a crash or a lost response between those
 * two steps still leaves this row for ReconcileCheckouts to find. Deleted by
 * DepositPaymentProcessor::createFromPayment() once the webhook (or the
 * reconciliation job itself) has actually built the Deposit — see CLAUDE.md.
 *
 * @property string $payment_intent_id
 */
#[Fillable(['payment_intent_id'])]
class PaymentIntentTracking extends Model
{
    protected $table = 'payment_intent_tracking';
}
