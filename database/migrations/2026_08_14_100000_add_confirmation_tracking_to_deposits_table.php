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
            // Set only once the client confirmation email
            // (DepositConfirmedNotification) has actually been sent
            // successfully — see DepositPaymentProcessor::createFromPayment().
            // With QUEUE_CONNECTION=sync in production (see DEPLOY.md §2),
            // a failed SMTP send is not retried by a queue worker; this
            // column is what lets a future catch-up job (see CLAUDE.md,
            // prompt 4) find a paid deposit whose confirmation never went
            // out and retry it.
            $table->timestamp('confirmation_sent_at')->nullable()->after('paid_at');
            // Incremented on every send attempt, successful or not — lets
            // a catch-up job cap how many times it retries a persistently
            // failing address instead of retrying forever.
            $table->unsignedInteger('confirmation_attempts')->default(0)->after('confirmation_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('deposits', function (Blueprint $table) {
            $table->dropColumn(['confirmation_sent_at', 'confirmation_attempts']);
        });
    }
};
