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
            $table->string('type')->default('gallery');
            // Real DB-level dedup constraint for HomeGallerySeeder (and
            // any future admin-driven creation): two rows of the same type
            // can never share a position. Supersedes the plain single-column
            // index that would otherwise be needed here on `type` alone —
            // this composite one already covers lookups filtered by type.
            $table->unique(['type', 'position']);
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
