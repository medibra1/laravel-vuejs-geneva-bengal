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
        // No content columns on purpose: this table exists only so
        // spatie/laravel-medialibrary has a model to morph each
        // RichTextEditor image upload to — see EditorUpload.
        Schema::create('editor_uploads', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('editor_uploads');
    }
};
