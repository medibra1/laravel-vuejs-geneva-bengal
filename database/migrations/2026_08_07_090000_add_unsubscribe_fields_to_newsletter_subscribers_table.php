<?php

use App\Models\NewsletterSubscriber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->string('unsubscribe_token', 64)->nullable()->unique()->after('email');
            $table->timestamp('unsubscribed_at')->nullable()->after('unsubscribe_token');
        });

        // Backfill existing rows so nobody who subscribed before this
        // migration is left without a working unsubscribe link.
        NewsletterSubscriber::query()->whereNull('unsubscribe_token')->each(
            fn (NewsletterSubscriber $subscriber) => $subscriber->update(['unsubscribe_token' => Str::random(48)])
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('newsletter_subscribers', function (Blueprint $table) {
            $table->dropColumn(['unsubscribe_token', 'unsubscribed_at']);
        });
    }
};
