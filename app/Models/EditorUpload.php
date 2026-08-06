<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * One row per image inserted through RichTextEditor.vue (pages.body only).
 * Holds no content of its own — it exists so medialibrary has a model to
 * morph the upload to before the page itself is saved.
 */
class EditorUpload extends Model implements HasMedia
{
    use InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('editor-uploads')->singleFile();
    }
}
