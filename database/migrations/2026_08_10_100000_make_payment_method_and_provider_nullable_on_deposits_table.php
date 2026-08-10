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
            // Admin-recorded reservations can now be created without
            // picking a payment method yet ("à définir plus tard") — the
            // admin chooses it later, at the moment of actually marking
            // the deposit paid (see Admin\DepositController::markPaid()
            // and CLAUDE.md). The column's default ('stripe') is left
            // untouched: it only ever applies when the column is omitted
            // from an INSERT, which remains true for the public flow —
            // the admin flow now always passes payment_method explicitly,
            // including null.
            $table->string('payment_method')->nullable()->change();
            // provider is set from payment_method verbatim in
            // Admin\DepositController::store() (see CLAUDE.md — it mirrors
            // payment_method rather than defaulting to "stripe", so a
            // cash/bank_transfer/twint_manual deposit is never
            // misrepresented as having gone through Stripe). Without this,
            // an admin picking "à définir plus tard" would explicitly
            // insert NULL into a NOT NULL column and the query would fail.
            $table->string('provider')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->string('payment_method')->default('stripe')->change();
            $table->string('provider')->default('stripe')->change();
        });
    }
};
