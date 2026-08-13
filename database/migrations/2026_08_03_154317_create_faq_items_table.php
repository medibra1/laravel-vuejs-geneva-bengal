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
        Schema::create('faq_items', function (Blueprint $table) {
            $table->id();
            // Derived from the French question text (Str::slug), same
            // convention as Page::getSlugOptions() — the real DB-level
            // dedup constraint for ContentPagesSeeder::seedFaqItems(),
            // replacing an in-memory array_search against existing
            // questions.
            $table->string('slug')->unique();
            $table->json('question');
            $table->json('answer');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faq_items');
    }
};
