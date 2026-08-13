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
        Schema::create('checkout_holds', function (Blueprint $table) {
            $table->id();
            // Unique, not just indexed: at most one live checkout hold per
            // cat, enforced at the SQL level so a race between two
            // acquire() calls can never both succeed even under a bug in
            // the application-level lockForUpdate() logic.
            $table->foreignId('cat_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('payment_intent_id')->unique();
            // Sliding — pushed forward by touch() while the payment page
            // stays open, so an active visitor is never cut off.
            $table->timestamp('expires_at');
            // Fixed at creation, never touched again — the ceiling for an
            // abandoned-but-still-open tab. Without this, unlimited
            // touch() pings would make the hold effectively permanent.
            $table->timestamp('hard_expires_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('checkout_holds');
    }
};
