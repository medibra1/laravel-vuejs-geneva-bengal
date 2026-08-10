<?php

use App\Models\Color;
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
        Schema::table('colors', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
        });

        // Backfill colors that already existed before this migration —
        // HasSlug (added on the model in this same change) only generates
        // a slug for rows created/updated from now on.
        Color::query()->whereNull('slug')->each(
            fn (Color $color) => $color->update(['slug' => Str::slug($color->name)])
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('colors', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
