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
        Schema::table('galleries', function (Blueprint $table) {
            // Default 'gallery' backfills every existing row as-is —
            // hero slider slides and homepage social tiles are new usages
            // of this table, introduced by this column.
            $table->string('type')->default('gallery')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('galleries', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
