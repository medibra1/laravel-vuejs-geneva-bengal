<?php

namespace App\Services\Payments;

/**
 * Everything a checkout needs to create a PaymentIntent, before any Deposit
 * row exists (see CLAUDE.md — store() no longer creates one up front). All
 * of this rides along as Stripe PaymentIntent metadata so the webhook can
 * build the real Deposit once the payment is actually confirmed.
 */
final readonly class CheckoutData
{
    public function __construct(
        public ?int $catId,
        public string $name,
        public string $email,
        public ?string $phone,
        public string $locale,
        public int $amount,
        public string $currency,
    ) {}
}
