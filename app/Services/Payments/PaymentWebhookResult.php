<?php

namespace App\Services\Payments;

use App\Enums\DepositStatus;

final readonly class PaymentWebhookResult
{
    public function __construct(
        public bool $handled,
        public ?int $depositId = null,
        public ?string $providerReference = null,
        public ?DepositStatus $status = null,
    ) {}
}
