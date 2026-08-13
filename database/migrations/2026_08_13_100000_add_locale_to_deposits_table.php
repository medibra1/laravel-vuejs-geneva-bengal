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
            // Captured once, at creation, from the visitor's active site
            // locale (Public\DepositController::store()) — the only point
            // where it's known. DepositConfirmedNotification/
            // DepositUnavailableNotification are triggered later by the
            // Stripe webhook or the daily reconciliation job, neither of
            // which has any notion of "the current visitor's language".
            // Null for admin-created deposits (payment_method !== stripe,
            // no public visitor involved) — those notifications stay
            // French by default, see NotifiesStaff usage.
            $table->string('locale', 5)->nullable()->after('provider');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
