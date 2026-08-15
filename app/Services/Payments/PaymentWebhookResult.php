<?php

namespace App\Services\Payments;

final readonly class PaymentWebhookResult
{
    /**
     * @param  array<string, string>  $metadata  Checkout data carried on the
     *                                           PaymentIntent since store()
     *                                           stopped creating a Deposit
     *                                           up front — see CheckoutData
     *                                           and StripeGateway::createPaymentIntent().
     */
    public function __construct(
        public bool $handled,
        public ?string $paymentIntentId = null,
        public array $metadata = [],
        public ?int $amount = null,
        public ?string $currency = null,
    ) {}
}
