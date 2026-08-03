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
        Schema::create('cats', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type');
            $table->string('sex');
            $table->foreignId('color_id')->constrained();
            $table->foreignId('second_color_id')->nullable()->constrained('colors');
            $table->json('description')->nullable();
            $table->unsignedInteger('price')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('eye_color')->nullable();
            $table->date('available_at')->nullable();
            $table->string('diet')->nullable();
            $table->boolean('litter_trained')->default(false);
            $table->boolean('neutered')->default(false);
            // No FK constraint yet: litters lands in Phase 2. Added there.
            $table->unsignedBigInteger('litter_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cats');
    }
};
