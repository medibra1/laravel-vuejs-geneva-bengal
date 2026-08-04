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
        Schema::table('owners', function (Blueprint $table) {
            // Both nullable and independent: a specific cat if one's
            // already been picked out, a color if they're on the waiting
            // list without one yet. Neither implies the other.
            $table->foreignId('desired_cat_id')->nullable()->after('city')->constrained('cats')->nullOnDelete();
            $table->foreignId('desired_color_id')->nullable()->after('desired_cat_id')->constrained('colors')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('owners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('desired_cat_id');
            $table->dropConstrainedForeignId('desired_color_id');
        });
    }
};
