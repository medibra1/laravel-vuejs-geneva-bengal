<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Stripe = 'stripe';
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case TwintManual = 'twint_manual';
}
