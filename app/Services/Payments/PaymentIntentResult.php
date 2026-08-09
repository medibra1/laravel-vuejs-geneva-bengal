<?php

namespace App\Services\Payments;

final readonly class PaymentIntentResult
{
    public function __construct(
        public string $id,
        public string $clientSecret,
        public string $url,
    ) {}
}
