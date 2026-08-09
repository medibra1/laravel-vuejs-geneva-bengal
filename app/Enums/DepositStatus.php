<?php

namespace App\Enums;

enum DepositStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    /**
     * Lost the atomic re-check in DepositPaymentProcessor::markPaid() — its
     * PaymentIntent authorization was cancelled (never captured) because
     * another deposit for the same cat was already paid. Distinct from
     * Cancelled (an abandoned/expired checkout) and Refunded (money that
     * did move and was given back) — here nothing was ever charged.
     */
    case Unavailable = 'unavailable';
}
