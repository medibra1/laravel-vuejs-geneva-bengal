<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEditorUploadRequest;
use App\Models\EditorUpload;
use Illuminate\Http\JsonResponse;

class EditorUploadController extends Controller
{
    /**
     * Image upload target for RichTextEditor.vue's image button. Returns a
     * URL to insert into the editor's HTML — never base64, so pages.body
     * stays small and the image is cacheable/servable directly from disk.
     */
    public function store(StoreEditorUploadRequest $request): JsonResponse
    {
        $upload = EditorUpload::create();
        $media = $upload->addMedia($request->file('image'))->toMediaCollection('editor-uploads');

        return response()->json(['url' => $media->getUrl()]);
    }
}
