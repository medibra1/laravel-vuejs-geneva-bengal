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
        Schema::table('deposits', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('cat_id')->constrained()->nullOnDelete();
            $table->string('payment_method')->default('stripe')->after('provider'); // stripe|cash|bank_transfer|twint_manual
            // The Stripe Checkout URL generated for an admin-created deposit
            // (payment_method = stripe) — persisted so the admin can come
            // back to the list and copy it, rather than only ever seeing it
            // once at creation time. Null for every other payment method
            // and for deposits created through the public flow (the
            // customer's browser is redirected there directly instead).
            $table->text('payment_link_url')->nullable()->after('provider_reference');
            // Null when created through the public checkout flow.
            $table->foreignId('created_by')->nullable()->after('payment_method')->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable()->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['payment_method', 'payment_link_url', 'finalized_at']);
        });
    }
};
