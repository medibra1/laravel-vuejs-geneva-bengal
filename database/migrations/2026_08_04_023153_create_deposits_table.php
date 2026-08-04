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
        Schema::create('deposits', function (Blueprint $table) {
            $table->id();
            // Nullable: a deposit can reserve a specific cat, or just be a
            // generic waiting-list registration — see CLAUDE.md.
            $table->foreignId('cat_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->unsignedInteger('amount');
            $table->string('currency', 3)->default('CHF');
            $table->string('status')->default('pending');
            $table->string('provider')->default('stripe');
            $table->string('provider_reference')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deposits');
    }
};
