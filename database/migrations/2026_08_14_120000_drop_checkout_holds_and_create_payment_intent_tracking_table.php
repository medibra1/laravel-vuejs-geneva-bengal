<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // CheckoutHold blocked every visitor arriving on a checkout page,
        // not just the ones who actually committed to paying — replaced by
        // a PaymentIntent created only at the "Pay" click, arbitrated after
        // the fact by DepositPaymentProcessor's own lockForUpdate() (see
        // CLAUDE.md).
        Schema::dropIfExists('checkout_holds');

        // No lock, no TTL, no uniqueness on anything — unlike CheckoutHold,
        // this is not a reservation mechanism, purely a breadcrumb so
        // ReconcileCheckouts can find a PaymentIntent whose webhook never
        // arrived. Written right after Stripe confirms the PaymentIntent
        // was created, before the client_secret is even returned to the
        // browser — see Public\DepositController::confirmIntent() and
        // CLAUDE.md.
        Schema::create('payment_intent_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('payment_intent_id')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_intent_tracking');

        Schema::create('checkout_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cat_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('payment_intent_id')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('hard_expires_at');
            $table->timestamps();
        });
    }
};
